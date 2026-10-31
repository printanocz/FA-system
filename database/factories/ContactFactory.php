<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Deterministické hodnoty: náhodná jména a e-maily z Fakeru občas obsahovala hledaný text
        // (např. „nic“ v monica@…) a vyhledávací testy tím nárazově padaly.
        static $sequence = 0;
        $n = ++$sequence;

        return [
            'type' => 'company',
            'name' => "Firma {$n}",
            'ico' => sprintf('9%07d', $n),
            'dic' => null,
            'street' => "Ulice {$n}",
            'city' => 'Město',
            'zip' => sprintf('%05d', 90000 + $n % 10000),
            'country' => 'CZ',
            'email' => "kontakt-{$n}@example.test",
            'email_cc' => null,
            'phone' => null,
            'default_due_days' => null,
            'default_discount_percent' => null,
        ];
    }
}
