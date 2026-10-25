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
        return [
            'type' => 'company',
            'name' => fake()->company(),
            'ico' => (string) fake()->numerify('########'),
            'dic' => null,
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'zip' => fake()->numerify('#####'),
            'country' => 'CZ',
            'email' => fake()->unique()->safeEmail(),
            'email_cc' => null,
            'phone' => null,
            'default_due_days' => null,
            'default_discount_percent' => null,
        ];
    }
}
