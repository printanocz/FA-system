<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the empty dashboard shell', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

it('serves the health check', function () {
    $this->get('/up')->assertOk();
});
