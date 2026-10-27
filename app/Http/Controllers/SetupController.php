<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Založení prvního uživatele bez SSH. Existuje jen dokud v databázi není žádný uživatel
 * a v `.env` je SETUP_TOKEN (nejméně 40 znaků) shodný s tokenem v adrese. Jinak 404.
 */
class SetupController extends Controller
{
    public function show(Request $request): Response
    {
        $token = $this->authorizeSetup($request);

        return Inertia::render('auth/Setup', ['token' => $token]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSetup($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => PasswordPolicy::rules(confirmed: true),
        ]);

        User::query()->create([
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'password' => $data['password'],
        ]);

        return redirect('/login')->with('status', 'Uživatel byl vytvořen. Přihlaste se, dvoufázové ověření zapnete při prvním přihlášení.');
    }

    /** Vrací token z adresy, nebo skončí 404. Dotaz do databáze až po ověření tokenu. */
    private function authorizeSetup(Request $request): string
    {
        $expected = (string) config('deploy.setup_token');
        $given = (string) $request->input('token', '');

        abort_if(
            strlen($expected) < (int) config('deploy.min_token_length')
                || ! hash_equals($expected, $given)
                || User::query()->exists(),
            404,
        );

        return $given;
    }
}
