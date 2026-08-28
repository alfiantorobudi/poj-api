<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    SanctumServiceProvider::class,
];
