<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\DeleteBankAccount;
use App\Actions\Settings\SaveBankAccount;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function store(Request $request, SaveBankAccount $save): RedirectResponse
    {
        $save($request->only(['name', 'account_number', 'bank_code', 'currency', 'swift', 'is_default']));

        return back();
    }

    public function update(Request $request, BankAccount $bankAccount, SaveBankAccount $save): RedirectResponse
    {
        $save($request->only(['name', 'account_number', 'bank_code', 'currency', 'swift', 'is_default']), $bankAccount);

        return back();
    }

    public function destroy(BankAccount $bankAccount, DeleteBankAccount $delete): RedirectResponse
    {
        $delete($bankAccount);

        return back();
    }
}
