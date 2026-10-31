<?php

use App\Domain\Backup\BackupCrypto;
use App\Domain\Backup\BackupException;
use App\Domain\Backup\RetentionPolicy;
use Illuminate\Support\Carbon;

function cryptoKey(): string
{
    return base64_encode(random_bytes(32));
}

/** @return array{0: string, 1: string, 2: string} zdroj, šifrovaný, dešifrovaný */
function cryptoFiles(string $content): array
{
    $source = tempnam(sys_get_temp_dir(), 'crypto');
    file_put_contents($source, $content);

    return [$source, $source.'.enc', $source.'.dec'];
}

describe('encryption', function () {
    it('decrypts back to the identical bytes', function (int $size) {
        $crypto = BackupCrypto::fromBase64(cryptoKey());
        [$source, $encrypted, $decrypted] = cryptoFiles($size === 0 ? '' : random_bytes($size));

        $crypto->encrypt($source, $encrypted);
        $crypto->decrypt($encrypted, $decrypted);

        expect(hash_file('sha256', $decrypted))->toBe(hash_file('sha256', $source))
            ->and(file_get_contents($encrypted))->toStartWith(BackupCrypto::MAGIC);
        if ($size > 0) {
            expect(file_get_contents($encrypted))->not->toContain(substr((string) file_get_contents($source), 0, 32));
        }
    })->with([0, 1, 65535, 65536, 65537, 200000]);

    it('rejects a wrong key', function () {
        [$source, $encrypted, $decrypted] = cryptoFiles(random_bytes(1000));
        BackupCrypto::fromBase64(cryptoKey())->encrypt($source, $encrypted);

        expect(fn () => BackupCrypto::fromBase64(cryptoKey())->decrypt($encrypted, $decrypted))
            ->toThrow(BackupException::class, 'špatný klíč');
    });

    it('rejects a modified file', function () {
        $crypto = BackupCrypto::fromBase64(cryptoKey());
        [$source, $encrypted, $decrypted] = cryptoFiles(random_bytes(5000));
        $crypto->encrypt($source, $encrypted);
        $raw = (string) file_get_contents($encrypted);
        $raw[100] = chr(ord($raw[100]) ^ 1);
        file_put_contents($encrypted, $raw);

        expect(fn () => $crypto->decrypt($encrypted, $decrypted))->toThrow(BackupException::class);
    });

    it('rejects a truncated file', function () {
        $crypto = BackupCrypto::fromBase64(cryptoKey());
        [$source, $encrypted, $decrypted] = cryptoFiles(random_bytes(200000));
        $crypto->encrypt($source, $encrypted);
        file_put_contents($encrypted, substr((string) file_get_contents($encrypted), 0, -100));

        expect(fn () => $crypto->decrypt($encrypted, $decrypted))->toThrow(BackupException::class, 'useknutá');
    });

    it('rejects a file that is not a backup', function () {
        [$source, , $decrypted] = cryptoFiles('obyčejný text, který není záloha, ale je dost dlouhý');

        expect(fn () => BackupCrypto::fromBase64(cryptoKey())->decrypt($source, $decrypted))
            ->toThrow(BackupException::class, 'hlavička');
    });

    it('requires a valid base64 key of 32 bytes', function (string $key) {
        expect(fn () => BackupCrypto::fromBase64($key))->toThrow(BackupException::class, 'BACKUP_KEY');
    })->with(['', 'abc', '!!!nebase64!!!', base64_encode('short')]);
});

describe('retention', function () {
    /** @return list<string> */
    function backupNames(string $latest, int $days, bool $twice = false): array
    {
        $names = [];
        for ($i = 0; $i < $days; $i++) {
            $names[] = RetentionPolicy::filename(Carbon::parse($latest)->subDays($i));
            if ($twice) {
                $names[] = RetentionPolicy::filename(Carbon::parse($latest)->subDays($i)->setTime(14, 0));
            }
        }

        return $names;
    }

    it('keeps the last 14 days and one backup per month', function () {
        $policy = new RetentionPolicy(14, 120);
        $names = backupNames('2026-10-31 02:30:00', 120);
        $expired = $policy->expired($names, Carbon::parse('2026-10-31 12:00:00'));
        $kept = array_values(array_diff($names, $expired));

        // Posledních 14 dní (31. 10. až 18. 10.) + poslední záloha každého staršího měsíce v datech (30. 9., 31. 8., 31. 7.).
        expect($kept)->toContain(RetentionPolicy::filename(Carbon::parse('2026-10-18 02:30:00')))
            ->and($kept)->not->toContain(RetentionPolicy::filename(Carbon::parse('2026-10-17 02:30:00')))
            ->and($kept)->toContain(RetentionPolicy::filename(Carbon::parse('2026-09-30 02:30:00')))
            ->and($kept)->toContain(RetentionPolicy::filename(Carbon::parse('2026-08-31 02:30:00')))
            ->and($kept)->not->toContain(RetentionPolicy::filename(Carbon::parse('2026-09-29 02:30:00')))
            ->and(count($kept))->toBe(14 + 3);
    });

    it('keeps only the newest backup of a day', function () {
        $policy = new RetentionPolicy(14, 120);
        $morning = RetentionPolicy::filename(Carbon::parse('2026-10-30 02:30:00'));
        $afternoon = RetentionPolicy::filename(Carbon::parse('2026-10-30 14:00:00'));

        expect($policy->expired([$morning, $afternoon], Carbon::parse('2026-10-31 12:00:00')))->toBe([$morning]);
    });

    it('keeps monthly backups for ten years and deletes older ones', function () {
        $policy = new RetentionPolicy(14, 120);
        $now = Carbon::parse('2026-10-31 12:00:00');
        $tenYears = RetentionPolicy::filename(Carbon::parse('2016-11-30 02:30:00'));
        $older = RetentionPolicy::filename(Carbon::parse('2016-10-31 02:30:00'));

        expect($policy->expired([$tenYears, $older], $now))->toBe([$older]);
    });

    it('never deletes files it does not recognize', function () {
        expect((new RetentionPolicy)->expired(['README.txt', 'splatno-spatne.zip.enc'], Carbon::parse('2026-10-31')))->toBe([]);
    });
});
