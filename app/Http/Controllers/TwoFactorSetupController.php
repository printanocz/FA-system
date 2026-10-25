<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

/**
 * Stránka průvodce nastavením 2FA. Samotné změny (zapnout, potvrdit kódem) dělají
 * routy Fortify pod /user/*; tady se jen vykreslí aktuální krok.
 */
class TwoFactorSetupController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $hasSecret = $user->two_factor_secret !== null;
        $confirmed = $user->two_factor_confirmed_at !== null;

        $step = match (true) {
            $confirmed => 'codes',
            $hasSecret => 'confirm',
            default => 'start',
        };

        return Inertia::render('auth/TwoFactorSetup', [
            'step' => $step,
            'qrCodeSvg' => $step === 'confirm' ? $user->twoFactorQrCodeSvg() : null,
            'secretKey' => $step === 'confirm'
                ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret)
                : null,
            'recoveryCodes' => $step === 'codes' ? $user->recoveryCodes() : [],
        ]);
    }
}
