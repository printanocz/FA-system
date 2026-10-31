<?php

namespace App\Actions\Settings;

use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;

/**
 * Smaže bankovní účet. Vystavené doklady mají vlastní snapshot účtu, takže se nemění.
 * Když se smaže výchozí účet, výchozím se stane nejstarší zbývající.
 */
final class DeleteBankAccount
{
    public function __invoke(BankAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $wasDefault = $account->is_default;
            $account->delete();

            if ($wasDefault) {
                BankAccount::query()->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });
    }
}
