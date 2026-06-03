<?php

use App\Modules\Shared\Providers\SharedServiceProvider;
use App\Modules\Talleres\Providers\TalleresServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SuperadminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SuperadminPanelProvider::class,
    TalleresServiceProvider::class,
    SharedServiceProvider::class,
];
