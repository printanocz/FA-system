<?php

namespace App\Models;

use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Údaje dodavatele (singleton: tabulka pustí jediný řádek díky unikátnímu sloupci `singleton`).
 */
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory;

    protected $table = 'company_profile';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'singleton' => 'boolean',
            'vat_payer' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->first();
    }

    /**
     * Údaje dodavatele uložené na dokladu při vystavení.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'name' => $this->name,
            'ico' => $this->ico,
            'dic' => $this->dic,
            'street' => $this->street,
            'city' => $this->city,
            'zip' => $this->zip,
            'country' => $this->country,
            'registration_note' => $this->registration_note,
            'email' => $this->email,
            'phone' => $this->phone,
            'web' => $this->web,
            'logo_path' => $this->logo_path,
            'signature_path' => $this->signature_path,
            'vat_payer' => $this->vat_payer,
        ];
    }
}
