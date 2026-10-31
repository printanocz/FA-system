<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanyProfile> */
class CompanyProfileFactory extends Factory
{
    protected $model = CompanyProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Adam Fujak',
            'ico' => '12345678',
            'dic' => null,
            'street' => 'Hlavní 1',
            'city' => 'Krnov',
            'zip' => '79401',
            'country' => 'CZ',
            'registration_note' => 'Fyzická osoba zapsaná v živnostenském rejstříku',
            'email' => 'fakturace@example.test',
            'phone' => '+420 777 000 000',
            'web' => 'https://adamfujak.cz',
            'vat_payer' => false,
        ];
    }

    public function vatPayer(): static
    {
        return $this->state(['vat_payer' => true, 'dic' => 'CZ12345678']);
    }
}
