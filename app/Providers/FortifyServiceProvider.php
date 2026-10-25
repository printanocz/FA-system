<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\GenericPasswordResetLinkFailedResponse;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            FailedPasswordResetLinkRequestResponse::class,
            GenericPasswordResetLinkFailedResponse::class,
        );
    }

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => Inertia::render('auth/Login'));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/ForgotPassword'));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ]));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));

        // 2FA výzva: 5 pokusů za minutu na rozpracované přihlášení + IP.
        RateLimiter::for('two-factor', function (Request $request): Limit {
            $key = $request->session()->get('login.id').'|'.$request->ip();

            return Limit::perMinute(5)->by($key)->response(
                function (Request $request, array $headers): RedirectResponse {
                    $seconds = (int) ($headers['Retry-After'] ?? 60);

                    return redirect()->route('two-factor.login')->withErrors([
                        'code' => __('auth.throttle', ['seconds' => $seconds]),
                    ]);
                },
            );
        });

        // Heslo se právě zadalo, takže ho nemusíme hned znovu potvrzovat před
        // nastavením 2FA. Přihlášení přes „zapamatovat“ cookie to nespouští.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->remember || ! request()->hasSession()) {
                return;
            }

            request()->session()->put('auth.password_confirmed_at', time());
        });
    }
}
