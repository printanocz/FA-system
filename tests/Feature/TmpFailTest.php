<?php

it('TEMP deliberately fails on the first iteration', function () {
    expect(getenv('PEST_ITER'))->not->toBe('1');
});
