<?php

declare(strict_types=1);

namespace App\Modules\Caja\Exceptions;

use Illuminate\Foundation\Exceptions\Exception as FoundationException;
use Illuminate\Http\Response;

class TurnoCerradoException extends FoundationException
{
    public function __construct(string $message = 'Error de turno de caja', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render(): \Illuminate\Http\Response
    {
        return response()->json([
            'error' => $this->message,
            'code' => $this->code,
        ], $this->code);
    }
}