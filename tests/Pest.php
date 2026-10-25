<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the Laravel application. Vite is disabled so tests
| do not depend on a built frontend (CI runs Pest before `npm run build`).
|
*/

pest()
    ->extend(TestCase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');
