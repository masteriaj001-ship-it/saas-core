<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Exceptions;

use RuntimeException;

class PlanLimitExceededException extends RuntimeException
{
    public function __construct(string $limitType, int $current, ?int $max)
    {
        $message = $max === null
            ? "Límite ilimitado alcanzado para {$limitType}."
            : "Límite de plan alcanzado: {$current}/{$max} para {$limitType}.";

        parent::__construct($message);
    }
}
