<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Přihlášený uživatel bez potvrzeného 2FA se nedostane nikam kromě nastavení 2FA.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
