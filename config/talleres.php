<?php

declare(strict_types=1);

return [
    'webhook_url' => env('TALLERES_WEBHOOK_URL', null),
    'webhook_timeout' => env('TALLERES_WEBHOOK_TIMEOUT', 5),
];
