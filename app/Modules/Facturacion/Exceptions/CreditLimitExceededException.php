<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Exceptions;

use App\Models\Contact;

class CreditLimitExceededException extends \RuntimeException
{
    public function __construct(
        public readonly Contact $contact,
        public readonly float $currentBalance,
        public readonly float $requestedAmount,
        public readonly float $creditLimit,
    ) {
        $newBalance = $this->currentBalance + $this->requestedAmount;

        parent::__construct(
            "El crédito de {$this->contact->name} excede el límite. "
            .'Balance actual: $'.number_format($this->currentBalance, 2)
            .', solicitado: $'.number_format($this->requestedAmount, 2)
            .', nuevo saldo: $'.number_format($newBalance, 2)
            .', límite: $'.number_format($this->creditLimit, 2)
        );
    }
}
