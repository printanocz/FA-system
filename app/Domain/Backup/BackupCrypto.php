<?php

namespace App\Domain\Backup;

/**
 * Šifrování záloh libsodiem: crypto_secretstream_xchacha20poly1305 po blocích 64 KiB,
 * takže paměť nezávisí na velikosti zálohy. Formát souboru .zip.enc:
 *
 *   "SPLBK1\0\0" (8 B) | hlavička streamu (24 B) | { délka bloku u32 BE | šifrovaný blok }…
 *
 * Poslední blok má tag FINAL; soubor bez něj (useknutý) nebo s přehozeným/upraveným blokem
 * se při dešifrování odmítne.
 */
final class BackupCrypto
{
    public const MAGIC = "SPLBK1\0\0";

    private const CHUNK = 65536;

    public function __construct(private readonly string $key) {}

    /** Klíč z `.env` (BACKUP_KEY, base64 o 32 bajtech). */
    public static function fromConfig(): self
    {
        return self::fromBase64((string) config('backup.key'));
    }

    public static function fromBase64(string $value): self
    {
        $key = $value === '' ? false : base64_decode(trim($value), true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new BackupException('BACKUP_KEY v .env chybí nebo není platný (base64 o 32 bajtech). Vygenerujte ho příkazem: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"');
        }

        return new self($key);
    }

    public function encrypt(string $plainPath, string $encryptedPath): void
    {
        $in = $this->open($plainPath, 'rb');
        $out = $this->open($encryptedPath, 'wb');

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key);
            $this->write($out, self::MAGIC.$header);

            $chunk = $this->read($in);
            do {
                $next = $this->read($in);
                $final = $next === '';
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    $chunk,
                    '',
                    $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
                );
                $this->write($out, pack('N', strlen($cipher)).$cipher);
                $chunk = $next;
            } while (! $final);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public function decrypt(string $encryptedPath, string $plainPath): void
    {
        $in = $this->open($encryptedPath, 'rb');
        $out = $this->open($plainPath, 'wb');

        try {
            $head = (string) fread($in, strlen(self::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (! str_starts_with($head, self::MAGIC) || strlen($head) !== strlen(self::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new BackupException('Soubor není šifrovaná záloha Splatna (špatná hlavička).');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($head, strlen(self::MAGIC)), $this->key);
            $finished = false;

            while (! feof($in)) {
                $lengthBytes = (string) fread($in, 4);
                if ($lengthBytes === '') {
                    break;
                }
                if ($finished || strlen($lengthBytes) !== 4) {
                    throw new BackupException('Záloha je poškozená (nečekaná data na konci).');
                }

                $length = (int) unpack('N', $lengthBytes)[1];
                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $length > self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                    throw new BackupException('Záloha je poškozená (neplatná délka bloku).');
                }

                $cipher = $this->readExactly($in, $length);
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new BackupException('Dešifrování selhalo: špatný klíč, nebo je záloha poškozená.');
                }

                [$plain, $tag] = $result;
                $this->write($out, $plain);
                $finished = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }

            if (! $finished) {
                throw new BackupException('Záloha je useknutá (chybí poslední blok).');
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** @return resource */
    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);

        if ($handle === false) {
            throw new BackupException("Soubor {$path} nejde otevřít.");
        }

        return $handle;
    }

    /** @param  resource  $handle */
    private function read($handle): string
    {
        $data = '';
        while (strlen($data) < self::CHUNK && ! feof($handle)) {
            $part = fread($handle, self::CHUNK - strlen($data));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }

        return $data;
    }

    /** @param  resource  $handle */
    private function readExactly($handle, int $length): string
    {
        $data = '';
        while (strlen($data) < $length && ! feof($handle)) {
            $part = fread($handle, $length - strlen($data));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }

        if (strlen($data) !== $length) {
            throw new BackupException('Záloha je useknutá (nedočtený blok).');
        }

        return $data;
    }

    /** @param  resource  $handle */
    private function write($handle, string $data): void
    {
        if ($data !== '' && fwrite($handle, $data) === false) {
            throw new BackupException('Zápis souboru selhal (je na disku místo?).');
        }
    }
}
