<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    /**
     * Změna hesla přihlášeného uživatele: staré heslo se musí shodovat, nové splní
     * stejnou politiku jako při resetu (min. 12 znaků).
     *
     * @param  mixed  $user
     * @param  array<string, mixed>  $input
     */
    public function update($user, array $input): void
    {
        assert($user instanceof User);

        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => PasswordPolicy::rules(confirmed: true),
        ], [
            'current_password.current_password' => 'Zadané heslo není správné.',
        ], [
            'current_password' => 'současné heslo',
        ])->validateWithBag('updatePassword');

        $user->forceFill(['password' => $input['password']])->save();
    }
}
