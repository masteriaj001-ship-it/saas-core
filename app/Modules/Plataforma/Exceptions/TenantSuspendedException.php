<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Exceptions;

use RuntimeException;

class TenantSuspendedException extends RuntimeException
{
    public function __construct(string $tenantName = '')
    {
        $message = $tenantName !== ''
            ? "El taller \"{$tenantName}\" está suspendido. Contacte al administrador."
            : 'Este taller está suspendido. Contacte al administrador.';

        parent::__construct($message);
    }
}
