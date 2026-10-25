<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the empty dashboard shell for a user with 2FA', function () {
    [$user] = twoFactorUser();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

it('serves the health check without login', function () {
    $this->get('/up')->assertOk();
});
