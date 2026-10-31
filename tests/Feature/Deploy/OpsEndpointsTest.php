<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->versionFile = sys_get_temp_dir().'/splatno-version-'.bin2hex(random_bytes(4));
    config(['deploy.version_file' => $this->versionFile]);
});

afterEach(function () {
    if (file_exists($this->versionFile)) {
        unlink($this->versionFile);
    }
});

describe('healthcheck', function () {
    it('is 404 without a token', function () {
        config(['deploy.health_token' => 'secret-health-token']);

        $this->get('/_health')->assertNotFound();
    });

    it('is 404 with a wrong token', function () {
        config(['deploy.health_token' => 'secret-health-token']);

        $this->get('/_health', ['X-Health-Token' => 'wrong'])->assertNotFound();
    });

    it('is 404 when no token is configured, even with an empty header', function () {
        config(['deploy.health_token' => '']);

        $this->get('/_health', ['X-Health-Token' => ''])->assertNotFound();
    });

    it('returns only the status, database, version, maintenance flag and backup age with the right token', function () {
        config(['deploy.health_token' => 'secret-health-token']);
        file_put_contents($this->versionFile, "abc123def\n");

        $this->getJson('/_health', ['X-Health-Token' => 'secret-health-token'])
            ->assertOk()
            ->assertExactJson(['app' => 'ok', 'db' => 'ok', 'version' => 'abc123def', 'maintenance' => false, 'backup_age_hours' => null]);
    });

    it('reports an unknown version before the first deployment', function () {
        config(['deploy.health_token' => 'secret-health-token']);

        $this->getJson('/_health', ['X-Health-Token' => 'secret-health-token'])
            ->assertOk()
            ->assertJsonPath('version', 'unknown');
    });

    it('answers during maintenance and says so', function () {
        config(['deploy.health_token' => 'secret-health-token']);
        $this->artisan('down')->assertSuccessful();

        try {
            $this->getJson('/_health', ['X-Health-Token' => 'secret-health-token'])
                ->assertOk()
                ->assertJsonPath('maintenance', true);
        } finally {
            $this->artisan('up');
        }
    });

    it('does not set a session cookie', function () {
        config(['deploy.health_token' => 'secret-health-token']);

        $response = $this->get('/_health', ['X-Health-Token' => 'secret-health-token']);

        expect($response->headers->getCookies())->toBe([]);
    });
});

describe('cron endpoint', function () {
    $token = str_repeat('c', 40);

    it('is 404 with a wrong token', function () use ($token) {
        config(['deploy.cron_token' => $token]);

        $this->get('/_cron/'.str_repeat('x', 40))->assertNotFound();
    });

    it('is 404 when CRON_TOKEN is not set', function () {
        config(['deploy.cron_token' => '']);

        $this->get('/_cron/anything')->assertNotFound();
    });

    it('is 404 when the configured token is shorter than 40 characters', function () {
        config(['deploy.cron_token' => 'short-token']);

        $this->get('/_cron/short-token')->assertNotFound();
    });

    it('runs the scheduler with the right token', function () use ($token) {
        config(['deploy.cron_token' => $token]);

        $this->get('/_cron/'.$token)->assertOk()->assertSee('ok');
    });

    it('works during maintenance, so the post-deploy can finish', function () use ($token) {
        config(['deploy.cron_token' => $token]);
        $this->artisan('down')->assertSuccessful();

        try {
            $this->get('/_cron/'.$token)->assertOk();
        } finally {
            $this->artisan('up');
        }
    });

    it('is rate limited', function () use ($token) {
        config(['deploy.cron_token' => $token]);

        foreach (range(1, 30) as $i) {
            $this->get('/_cron/wrong')->assertNotFound();
        }

        $this->get('/_cron/'.$token)->assertStatus(429);
    });
});

describe('first user setup', function () {
    $token = str_repeat('s', 40);

    function setupPayload(string $token, array $overrides = []): array
    {
        return array_merge([
            'token' => $token,
            'name' => 'Adam Fuják',
            'email' => 'Jsem@AdamFujak.cz',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ], $overrides);
    }

    it('is 404 without a token in the URL', function () use ($token) {
        config(['deploy.setup_token' => $token]);

        $this->get('/setup')->assertNotFound();
    });

    it('is 404 with a wrong token', function () use ($token) {
        config(['deploy.setup_token' => $token]);

        $this->get('/setup?token='.str_repeat('x', 40))->assertNotFound();
    });

    it('is 404 when SETUP_TOKEN is not configured', function () {
        config(['deploy.setup_token' => '']);

        $this->get('/setup?token=')->assertNotFound();
    });

    it('is 404 when the configured token is shorter than 40 characters', function () {
        config(['deploy.setup_token' => 'short']);

        $this->get('/setup?token=short')->assertNotFound();
    });

    it('is 404 once any user exists', function () use ($token) {
        config(['deploy.setup_token' => $token]);
        User::factory()->create();

        $this->get('/setup?token='.$token)->assertNotFound();
        $this->post('/setup', setupPayload($token))->assertNotFound();
    });

    it('shows the form with the right token', function () use ($token) {
        config(['deploy.setup_token' => $token]);

        $this->get('/setup?token='.$token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/Setup')->where('token', $token));
    });

    it('creates the first user and then disappears', function () use ($token) {
        config(['deploy.setup_token' => $token]);

        $this->post('/setup', setupPayload($token))
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $user = User::query()->sole();
        expect($user->email)->toBe('jsem@adamfujak.cz')
            ->and($user->name)->toBe('Adam Fuják')
            ->and(Hash::check('a-long-enough-password', $user->password))->toBeTrue();

        $this->get('/setup?token='.$token)->assertNotFound();
    });

    it('does not create anything with a wrong token', function () use ($token) {
        config(['deploy.setup_token' => $token]);

        $this->post('/setup', setupPayload(str_repeat('x', 40)))->assertNotFound();

        expect(User::query()->count())->toBe(0);
    });

    it('applies the password policy and validates the rest', function () use ($token) {
        config(['deploy.setup_token' => $token]);

        $this->post('/setup', setupPayload($token, ['password' => 'short', 'password_confirmation' => 'short', 'email' => 'nope', 'name' => '']))
            ->assertSessionHasErrors(['password', 'email', 'name']);

        $this->post('/setup', setupPayload($token, ['password_confirmation' => 'different-password-here']))
            ->assertSessionHasErrors('password');

        expect(User::query()->count())->toBe(0);
    });

    it('is not exempt from maintenance mode', function () use ($token) {
        config(['deploy.setup_token' => $token]);
        $this->artisan('down')->assertSuccessful();

        try {
            $this->get('/setup?token='.$token)->assertStatus(503);
        } finally {
            $this->artisan('up');
        }
    });
});
