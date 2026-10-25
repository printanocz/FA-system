<?php

it('runs in the Prague time zone and Czech locale', function () {
    expect(config('app.timezone'))->toBe('Europe/Prague')
        ->and(config('app.locale'))->toBe('cs');
});
