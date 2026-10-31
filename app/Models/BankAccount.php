<?php

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Výchozí účet je vždy nejvýš jeden.
        static::saved(function (BankAccount $account): void {
            if ($account->is_default) {
                static::query()
                    ->where('id', '!=', $account->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    public static function default(): ?self
    {
        return static::query()->orderByDesc('is_default')->orderBy('id')->first();
    }

    /**
     * Údaje účtu uložené na dokladu při vystavení.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'name' => $this->name,
            'account_number' => $this->account_number,
            'bank_code' => $this->bank_code,
            'iban' => $this->iban,
            'swift' => $this->swift,
            'currency' => $this->currency,
        ];
    }
}
