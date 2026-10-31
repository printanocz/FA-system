<?php

it('sends the security headers', function () {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('sends HSTS only in production', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->app->detectEnvironment(fn () => 'production');

    $this->get('/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('forbids all robots', function () {
    expect(file_get_contents(public_path('robots.txt')))->toContain('Disallow: /');
});

it('configures a hardened session', function () {
    expect(config('session.encrypt'))->toBeTrue()
        ->and(config('session.lifetime'))->toBe(120)
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.http_only'))->toBeTrue();
});
