<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SuperadminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SuperadminPanelProvider::class,
];
