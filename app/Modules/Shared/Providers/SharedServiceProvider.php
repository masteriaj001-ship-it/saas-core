<?php

declare(strict_types=1);

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Console\Commands\CreateSuperAdmin;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            CreateSuperAdmin::class,
        ]);
    }
}
