<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvoiceDocumentTypeEnum: string implements HasLabel
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Pos = 'pos';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => __('Factura de Venta'),
            self::CreditNote => __('Nota Crédito'),
            self::Pos => __('Documento Equivalente POS'),
        };
    }
}
