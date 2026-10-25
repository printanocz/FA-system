<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_cc' => 'array',
            'default_due_days' => 'integer',
            'default_discount_percent' => 'decimal:2',
            'ares_synced_at' => 'datetime',
        ];
    }

    /**
     * Údaje odběratele uložené na dokladu při vystavení. Pozdější změna kontaktu
     * už starý doklad nezmění.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'ico' => $this->ico,
            'dic' => $this->dic,
            'street' => $this->street,
            'city' => $this->city,
            'zip' => $this->zip,
            'country' => $this->country,
            'email' => $this->email,
            'email_cc' => $this->email_cc,
            'phone' => $this->phone,
        ];
    }
}
