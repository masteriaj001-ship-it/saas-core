<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Services\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrado como Singleton absoluto del ciclo de vida de la petición
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager;
        });

        $this->app->bind(
            \Filament\Auth\Http\Responses\Contracts\LoginResponse::class,
            LoginResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app()->setLocale('es');

        $this->loadTranslationsFrom(base_path('lang/vendor/filament-panels'), 'filament-panels');
        $this->loadTranslationsFrom(base_path('lang/vendor/filament-forms'), 'filament-forms');
        $this->loadTranslationsFrom(base_path('lang/vendor/filament-tables'), 'filament-tables');
        $this->loadTranslationsFrom(base_path('lang/vendor/filament-widgets'), 'filament-widgets');
        $this->loadTranslationsFrom(base_path('lang/vendor/filament-notifications'), 'filament-notifications');
        $this->loadTranslationsFrom(base_path('lang/vendor/filament-actions'), 'filament-actions');

        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        $this->guardDestructiveDatabaseCommands();
    }

    /**
     * Prevents destructive database commands from running against a target
     * database unless its name is explicitly allow-listed.
     *
     * This is a hard guardrail: commands like `migrate:fresh`, `migrate:refresh`,
     * `migrate:rollback`, `db:wipe` and `db:seed` (this seeder deletes rows) will
     * be aborted whenever the resolved target database is not present in the
     * comma-separated `DB_DESTRUCTIVE_ALLOWLIST` environment variable.
     *
     * Test/dusk databases are allowed implicitly via `DB_TEST_DATABASES`. No code
     * path can bypass this without an explicit env override.
     */
    private function guardDestructiveDatabaseCommands(): void
    {
        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event): void {
            $destructive = [
                'migrate:fresh',
                'migrate:refresh',
                'migrate:rollback',
                'db:wipe',
                'db:seed',
            ];

            if (! in_array($event->command, $destructive, true)) {
                return;
            }

            $connection = config('database.default');
            $target = (string) config("database.connections.{$connection}.database");

            $explicit = array_map(
                fn (string $name): string => trim($name),
                explode(',', (string) env('DB_DESTRUCTIVE_ALLOWLIST', '')),
            );

            $defaultTest = env('DB_TEST_DATABASE', 'testing');
            $allowed = array_values(array_unique(array_filter([...$explicit, $defaultTest])));

            if (in_array($target, $allowed, true)) {
                return;
            }

            throw new RuntimeException(
                'Bloqueado por AppServiceProvider::guardDestructiveDatabaseCommands(): '
                ."el comando `{$event->command}` destruiría la base de datos `{$target}`. "
                .'Agrega `'.$target.'` a DB_DESTRUCTIVE_ALLOWLIST solo para ejecutar comandos destructivos de forma intencional.',
            );
        });
    }
}
