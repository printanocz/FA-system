<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * Zvaliduje a uloží nové heslo po resetu e-mailem.
     *
     * @param  mixed  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        assert($user instanceof User);

        Validator::make($input, [
            'password' => PasswordPolicy::rules(confirmed: true),
        ])->validate();

        $user->forceFill(['password' => $input['password']])->save();
    }
}
