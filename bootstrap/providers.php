<?php

use App\Modules\Budget\Providers\BudgetServiceProvider;
use App\Modules\Facturacion\Providers\FacturacionServiceProvider;
use App\Modules\Inventario\Providers\InventarioServiceProvider;
use App\Modules\Shared\Providers\SharedServiceProvider;
use App\Modules\Talleres\Providers\TalleresServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SuperadminPanelProvider;

return [
    AdminPanelProvider::class,
    SuperadminPanelProvider::class,
    AppServiceProvider::class,
    BudgetServiceProvider::class,
    FacturacionServiceProvider::class,
    InventarioServiceProvider::class,
    SharedServiceProvider::class,
    TalleresServiceProvider::class,
];
