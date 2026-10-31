<?php

namespace App\Domain\Documents;

/** Přidělené číslo dokladu: hotový text a jeho složky (rok a pořadí v řadě). */
final readonly class AllocatedNumber
{
    public function __construct(
        public string $number,
        public int $year,
        public int $sequence,
    ) {}
}
