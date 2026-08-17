<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Exceptions;

use App\Modules\Facturacion\Models\Invoice;

final class PaymentExceedsBalanceException extends \RuntimeException
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly float $requested,
        public readonly float $balance,
    ) {
        parent::__construct(
            "El pago ({$requested}) excede el saldo pendiente ({$balance}) de la factura {$invoice->document_number}."
        );
    }
}
