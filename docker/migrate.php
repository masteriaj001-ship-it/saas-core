<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::statement("SET app.current_tenant_id = '00000000-0000-0000-0000-000000000000'");

$exitCode = Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();

exit($exitCode);
