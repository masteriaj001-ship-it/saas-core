<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Providers;

use App\Modules\Facturacion\Services\InvoiceCodeGenerator;
use Illuminate\Support\ServiceProvider;

class FacturacionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InvoiceCodeGenerator::class);
    }

    public function boot(): void {}
}
