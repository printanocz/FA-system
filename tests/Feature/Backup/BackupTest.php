<?php

use App\Domain\Backup\BackupCrypto;
use App\Domain\Backup\BackupService;
use App\Domain\Backup\BackupStatus;
use App\Domain\Backup\DatabaseDumper;
use App\Domain\Backup\RetentionPolicy;
use App\Models\Backup;
use App\Models\Contact;
use App\Models\Document;
use App\Models\PriceItem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** Zapne zálohy s náhodným klíčem; vrací klíč, aby šel zálohu dešifrovat. */
function backupKey(): string
{
    $key = base64_encode(random_bytes(32));
    config(['backup.key' => $key]);

    return $key;
}

/** Zapne off-site disk (S3 údaje vyplněné, úložiště je v testu lokální fake). */
function enableRemote(): void
{
    Storage::fake('backup');
    config(['filesystems.disks.backup.key' => 'k', 'filesystems.disks.backup.secret' => 's', 'filesystems.disks.backup.bucket' => 'b', 'filesystems.disks.backup.endpoint' => 'https://s3.example.test']);
}

/** @return list<string> jména souborů v ZIP archivu dešifrované zálohy */
function backupEntries(string $key, string $path): array
{
    $zipPath = tempnam(sys_get_temp_dir(), 'bk').'.zip';
    BackupCrypto::fromBase64($key)->decrypt($path, $zipPath);

    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();

    return $names;
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-31 02:30:00');
    [$this->user] = twoFactorUser();
    $this->actingAs($this->user);
});

afterEach(fn () => Carbon::setTestNow());

describe('database dump', function () {
    it('dumps the sqlite database so that it restores into an empty one', function () {
        Contact::factory()->create(['name' => "O'Brien & syn", 'ico' => '12345678']);
        Contact::factory()->count(2)->create();
        $path = tempnam(sys_get_temp_dir(), 'dump');

        app(DatabaseDumper::class)->dump($path);

        $restored = new PDO('sqlite::memory:');
        $restored->exec((string) file_get_contents($path));

        expect((int) $restored->query('select count(*) from contacts')->fetchColumn())->toBe(3)
            ->and($restored->query("select name from contacts where ico = '12345678'")->fetchColumn())->toBe("O'Brien & syn");
    });
});

describe('running a backup', function () {
    it('stores an encrypted backup locally with the database and the archive PDFs', function () {
        $key = backupKey();
        Contact::factory()->create(['name' => 'Pekárna Hrubý']);
        Storage::disk('local')->put('documents/2026/2026061.pdf', '%PDF-test');

        $backup = app(BackupService::class)->run();

        expect($backup->status)->toBe('ok')
            ->and($backup->filename)->toBe('splatno-20261031-023000.zip.enc')
            ->and($backup->stored_local)->toBeTrue()
            ->and($backup->stored_remote)->toBeFalse()
            ->and($backup->size)->toBeGreaterThan(0);
        Storage::disk('backups')->assertExists($backup->filename);
        Storage::disk('backup')->assertMissing($backup->filename);

        expect(backupEntries($key, Storage::disk('backups')->path($backup->filename)))
            ->toContain('database.sql')
            ->toContain('MANIFEST.json')
            ->toContain('storage/private/documents/2026/2026061.pdf');
    });

    it('also uploads off-site when the storage is configured', function () {
        backupKey();
        enableRemote();

        $backup = app(BackupService::class)->run();

        expect($backup->stored_remote)->toBeTrue();
        Storage::disk('backup')->assertExists($backup->filename);
    });

    it('fails with a clear message and no files when the key is missing', function () {
        config(['backup.key' => '']);

        $this->artisan('app:backup:run')->expectsOutputToContain('BACKUP_KEY')->assertFailed();

        $backup = Backup::query()->sole();
        expect($backup->status)->toBe('failed')->and($backup->error)->toContain('BACKUP_KEY');
        expect(Storage::disk('backups')->files())->toBe([]);
    });

    it('runs from the artisan command', function () {
        backupKey();

        $this->artisan('app:backup:run')->expectsOutputToContain('splatno-20261031-023000.zip.enc')->assertSuccessful();
    });

    it('is scheduled daily at 02:30', function () {
        $this->artisan('schedule:list')->expectsOutputToContain('app:backup:run')->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'app:backup:run'));

        expect($event->expression)->toBe('30 2 * * *');
    });

    it('deletes backups outside the retention on both disks', function () {
        backupKey();
        enableRemote();
        $old = RetentionPolicy::filename(Carbon::parse('2026-10-10 02:30:00'));
        $monthEnd = RetentionPolicy::filename(Carbon::parse('2026-09-30 02:30:00'));
        $midMonth = RetentionPolicy::filename(Carbon::parse('2026-09-15 02:30:00'));
        $recent = RetentionPolicy::filename(Carbon::parse('2026-10-25 02:30:00'));
        foreach (['backups', 'backup'] as $disk) {
            foreach ([$old, $monthEnd, $midMonth, $recent, 'poznamka.txt'] as $name) {
                Storage::disk($disk)->put($name, 'x');
            }
        }

        app(BackupService::class)->run();

        foreach (['backups', 'backup'] as $disk) {
            Storage::disk($disk)->assertMissing([$old, $midMonth]);
            Storage::disk($disk)->assertExists([$monthEnd, $recent, 'poznamka.txt', 'splatno-20261031-023000.zip.enc']);
        }
    });
});

describe('decrypt command', function () {
    it('decrypts a backup to a zip and refuses a wrong key', function () {
        $key = backupKey();
        $backup = app(BackupService::class)->run();
        $source = Storage::disk('backups')->path($backup->filename);
        $target = sys_get_temp_dir().'/dec-'.uniqid().'.zip';

        $this->artisan('app:backup:decrypt', ['soubor' => $source, 'cil' => $target])->assertSuccessful();
        expect(file_get_contents($target, false, null, 0, 2))->toBe('PK');

        config(['backup.key' => base64_encode(random_bytes(32))]);
        $this->artisan('app:backup:decrypt', ['soubor' => $source, 'cil' => $target.'.x'])->expectsOutputToContain('špatný klíč')->assertFailed();
        expect(is_file($target.'.x'))->toBeFalse();
        expect($key)->not->toBe(config('backup.key'));
    });

    it('fails for a missing file', function () {
        $this->artisan('app:backup:decrypt', ['soubor' => '/nonexistent.zip.enc', 'cil' => '/tmp/x.zip'])->assertFailed();
    });
});

describe('data export', function () {
    it('contains the PDFs of issued invoices and the CSV files', function () {
        $invoice = issueInvoice(draftInvoice([[1, 14500], [2, 600]], ['contact_id' => Contact::factory()->create(['name' => 'Pekárna Hrubý'])->id]), '2026-10-12');
        draftInvoice([[1, 100]]);
        PriceItem::factory()->create(['name' => 'Návrh loga', 'code' => 'LOGO']);

        $response = $this->get('/nastaveni/export')->assertOk();
        expect($response->headers->get('Content-Disposition'))->toContain('splatno-export-2026-10-31.zip');

        $zip = new ZipArchive;
        expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }

        expect($names)->toContain("faktury-pdf/Faktura-{$invoice->number}.pdf")
            ->toContain('faktury.csv', 'polozky.csv', 'uhrady.csv', 'kontakty.csv', 'cenik.csv')
            ->and(collect($names)->filter(fn ($name) => str_starts_with($name, 'faktury-pdf/'))->count())->toBe(1)
            ->and((string) $zip->getFromName("faktury-pdf/Faktura-{$invoice->number}.pdf"))->toStartWith('%PDF')
            ->and((string) $zip->getFromName('faktury.csv'))->toContain($invoice->number)->toContain('Pekárna Hrubý')
            ->and((string) $zip->getFromName('polozky.csv'))->toContain('14500.00')
            ->and((string) $zip->getFromName('cenik.csv'))->toContain('Návrh loga;LOGO');
        expect(Document::query()->count())->toBe(2);
    });

    it('requires a login', function () {
        auth()->logout();

        $this->get('/nastaveni/export')->assertRedirect();
    });
});

describe('status, banner and health', function () {
    it('warns when no backup has run yet', function () {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('backupWarning.message', 'Záloha dat zatím neproběhla.')
            ->where('backupWarning.age_hours', null));
    });

    it('shows no banner after a fresh backup', function () {
        Backup::query()->create(['filename' => 'a', 'status' => 'ok', 'stored_local' => true]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('backupWarning', null));
    });

    it('shows the banner when the last successful backup is older than 48 hours', function () {
        Backup::query()->create(['filename' => 'a', 'status' => 'ok', 'stored_local' => true, 'created_at' => Carbon::now()->subHours(50)]);
        Backup::query()->create(['status' => 'failed', 'error' => 'x']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('backupWarning.age_hours', 50)
            ->where('backupWarning.message', fn ($message) => str_contains((string) $message, '50 hodin')));
        expect(BackupStatus::ageHours())->toBe(50);
    });

    it('does not warn at exactly 48 hours', function () {
        Backup::query()->create(['filename' => 'a', 'status' => 'ok', 'stored_local' => true, 'created_at' => Carbon::now()->subHours(48)]);

        expect(BackupStatus::warning())->toBeNull();
    });

    it('reports the backup age in /_health', function () {
        config(['deploy.health_token' => 'secret-health-token']);
        Backup::query()->create(['filename' => 'a', 'status' => 'ok', 'stored_local' => true, 'created_at' => Carbon::now()->subHours(5)]);
        auth()->logout();

        $this->getJson('/_health', ['X-Health-Token' => 'secret-health-token'])->assertOk()->assertJsonPath('backup_age_hours', 5);
    });

    it('shows the state and the last runs in the settings', function () {
        config(['backup.key' => '']);
        Backup::query()->create(['filename' => 'splatno-20261030-023000.zip.enc', 'size' => 2048, 'status' => 'ok', 'stored_local' => true]);

        $this->get('/nastaveni')->assertInertia(fn (Assert $page) => $page
            ->where('backup.key_set', false)
            ->where('backup.offsite', false)
            ->where('backup.last.filename', 'splatno-20261030-023000.zip.enc')
            ->has('backup.recent', 1));
    });

    it('runs a backup from the settings page', function () {
        backupKey();

        $this->post('/nastaveni/zaloha')->assertRedirect()->assertSessionHas('status', 'Záloha hotová (jen lokálně)');

        expect(Backup::query()->where('status', 'ok')->count())->toBe(1);
    });

    it('reports a failed manual backup', function () {
        config(['backup.key' => '']);

        $this->post('/nastaveni/zaloha')->assertRedirect()->assertSessionHas('error');
    });
});
