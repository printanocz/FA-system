<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Neexistující e-mail dostane stejnou odpověď jako existující, aby formulář
 * neprozrazoval, kdo má v aplikaci účet.
 */
class GenericPasswordResetLinkFailedResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request)
    {
        if ($this->status === Password::INVALID_USER) {
            return back()->with('status', __('passwords.sent'));
        }

        throw ValidationException::withMessages([
            'email' => [trans($this->status)],
        ]);
    }
}
