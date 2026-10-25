<?php

use App\Deploy\CommandRunner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/** Záznamník místo skutečných Artisan příkazů (optimize by v testech zapsal cache konfigurace). */
class FakeCommandRunner extends CommandRunner
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, array<string, mixed>> */
    public array $parameters = [];

    public ?string $failOn = null;

    public function run(string $command, array $parameters = []): int
    {
        $this->calls[] = $command;
        $this->parameters[$command] = $parameters;

        return $command === $this->failOn ? 1 : 0;
    }
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/splatno-deploy-'.bin2hex(random_bytes(4));
    mkdir($this->dir);

    config([
        'deploy.marker' => $this->dir.'/deploy.pending',
        'deploy.version_file' => $this->dir.'/deployed-version',
        'deploy.lock_file' => $this->dir.'/deploy.lock',
        'logging.channels.deploy' => ['driver' => 'single', 'path' => $this->dir.'/deploy.log', 'level' => 'debug'],
    ]);

    $this->runner = new FakeCommandRunner;
    $this->app->instance(CommandRunner::class, $this->runner);
});

afterEach(function () {
    foreach (glob($this->dir.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($this->dir);
});

it('does nothing without the marker', function () {
    $this->artisan('app:post-deploy')->assertSuccessful();

    expect($this->runner->calls)->toBe([])
        ->and(file_exists($this->dir.'/deployed-version'))->toBeFalse();
});

it('migrates, seeds, caches and brings the app up when the marker exists', function () {
    file_put_contents($this->dir.'/deploy.pending', "abc123def\n");

    $this->artisan('app:post-deploy')->assertSuccessful();

    expect($this->runner->calls)->toBe(['migrate', 'db:seed', 'optimize', 'view:cache', 'up'])
        ->and($this->runner->parameters['migrate'])->toBe(['--force' => true])
        ->and($this->runner->parameters['db:seed'])->toBe(['--class' => 'Database\Seeders\InitialSetupSeeder', '--force' => true])
        ->and(trim((string) file_get_contents($this->dir.'/deployed-version')))->toBe('abc123def')
        ->and(file_exists($this->dir.'/deploy.pending'))->toBeFalse();
});

it('logs the result with the version', function () {
    file_put_contents($this->dir.'/deploy.pending', 'abc123def');

    $this->artisan('app:post-deploy')->assertSuccessful();

    $log = (string) file_get_contents($this->dir.'/deploy.log');
    expect($log)->toContain('Nasazení začalo')
        ->and($log)->toContain('Nasazení dokončeno')
        ->and($log)->toContain('abc123def');
});

it('is idempotent: the second run finds no marker', function () {
    file_put_contents($this->dir.'/deploy.pending', 'abc123def');

    $this->artisan('app:post-deploy')->assertSuccessful();
    $this->artisan('app:post-deploy')->assertSuccessful();

    expect($this->runner->calls)->toHaveCount(5);
});

it('keeps the marker, logs the error and puts the app into maintenance when a migration fails', function () {
    file_put_contents($this->dir.'/deploy.pending', 'abc123def');
    $this->runner->failOn = 'migrate';

    $this->artisan('app:post-deploy')->assertFailed();

    $log = (string) file_get_contents($this->dir.'/deploy.log');
    expect(file_exists($this->dir.'/deploy.pending'))->toBeTrue()
        ->and(file_exists($this->dir.'/deployed-version'))->toBeFalse()
        ->and($this->runner->calls)->toBe(['migrate', 'down'])
        ->and($this->runner->calls)->not->toContain('up')
        ->and($log)->toContain('Nasazení selhalo')
        ->and($log)->toContain('migrate skončil kódem 1');
});

it('can be retried after a failure', function () {
    file_put_contents($this->dir.'/deploy.pending', 'abc123def');
    $this->runner->failOn = 'migrate';
    $this->artisan('app:post-deploy')->assertFailed();

    $this->runner->failOn = null;
    $this->runner->calls = [];
    $this->artisan('app:post-deploy')->assertSuccessful();

    expect($this->runner->calls)->toBe(['migrate', 'db:seed', 'optimize', 'view:cache', 'up'])
        ->and(file_exists($this->dir.'/deploy.pending'))->toBeFalse();
});

it('does not run while another run holds the lock', function () {
    file_put_contents($this->dir.'/deploy.pending', 'abc123def');
    $lock = fopen($this->dir.'/deploy.lock', 'c');
    flock($lock, LOCK_EX);

    $this->artisan('app:post-deploy')->assertSuccessful();

    expect($this->runner->calls)->toBe([])
        ->and(file_exists($this->dir.'/deploy.pending'))->toBeTrue();

    flock($lock, LOCK_UN);
    fclose($lock);
});

it('runs every minute even in maintenance mode', function () {
    Artisan::all();
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'app:post-deploy'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->evenInMaintenanceMode)->toBeTrue();
});

it('is registered as an artisan command', function () {
    expect(array_key_exists('app:post-deploy', Artisan::all()))->toBeTrue();
});
