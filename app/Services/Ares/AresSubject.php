<?php

namespace App\Services\Ares;

/** Údaje o subjektu z ARES, zploštělé na to, co potřebuje Adresář. */
final readonly class AresSubject
{
    public function __construct(
        public string $name,
        public string $ico,
        public ?string $dic,
        public ?string $street,
        public ?string $city,
        public ?string $zip,
        public string $country,
    ) {}

    /**
     * Mapuje odpověď /ekonomicke-subjekty/{ico}. Ulice = název ulice (jinak části obce
     * či obce) + číslo popisné a orientační („Jankovcova 1522/53“).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(array $data): self
    {
        /** @var array<string, mixed> $seat */
        $seat = is_array($data['sidlo'] ?? null) ? $data['sidlo'] : [];

        $base = self::text($seat['nazevUlice'] ?? null)
            ?? self::text($seat['nazevCastiObce'] ?? null)
            ?? self::text($seat['nazevObce'] ?? null);

        $number = self::text($seat['cisloDomovni'] ?? null);
        $orientation = self::text($seat['cisloOrientacni'] ?? null);
        if ($orientation !== null) {
            $number = ($number ?? '').'/'.$orientation.(self::text($seat['cisloOrientacniPismeno'] ?? null) ?? '');
        }

        $street = $base === null && $number === null
            ? null
            : trim(($base ?? '').' '.($number ?? ''));

        $zip = self::text($seat['psc'] ?? null);

        return new self(
            name: (string) ($data['obchodniJmeno'] ?? ''),
            ico: (string) ($data['ico'] ?? ''),
            dic: self::text($data['dic'] ?? null),
            street: $street,
            city: self::text($seat['nazevObce'] ?? null),
            // ARES vrací PSČ jako číslo, takže ztrácí úvodní nulu (např. 1000 místo 01000).
            zip: $zip === null ? null : str_pad($zip, 5, '0', STR_PAD_LEFT),
            country: self::text($seat['kodStatu'] ?? null) ?? 'CZ',
        );
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param  array<string, string|null>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            ico: (string) $data['ico'],
            dic: $data['dic'],
            street: $data['street'],
            city: $data['city'],
            zip: $data['zip'],
            country: (string) $data['country'],
        );
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
