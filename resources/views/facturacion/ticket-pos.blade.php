<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->document_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Roboto Mono', 'DejaVu Sans Mono', monospace;
            font-size: 11px;
            color: #000;
            width: 80mm;
            margin: 0 auto;
            padding: 4mm;
            line-height: 1.35;
        }
        .center { text-align: center; }
        .header { margin-bottom: 6px; }
        .header .name { font-size: 14px; font-weight: 700; }
        .header .meta { font-size: 10px; }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
            height: 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }
        .row .label { white-space: nowrap; }
        .items .line { display: flex; width: 100%; }
        .items .line .qty { width: 10%; }
        .items .line .name {
            width: 55%;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .items .line .amt { width: 35%; text-align: right; }
        .totals .row { font-size: 11px; }
        .totals .grand-total {
            font-size: 13px;
            font-weight: 700;
            border-top: 1px solid #000;
            padding-top: 4px;
        }
        .payment .row { font-size: 11px; }
        .footer {
            margin-top: 8px;
            font-size: 9px;
            text-align: center;
        }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header center">
        <div class="name">{{ $tenant?->name ?? 'Taller' }}</div>
        @if ($tenant?->settings['pos']['footer'] ?? null)
            <div class="meta">{{ $tenant->settings['pos']['footer'] }}</div>
        @endif
    </div>

    <div class="divider"></div>

    <div class="row"><span>Factura:</span><span>{{ $invoice->document_number }}</span></div>
    <div class="row"><span>Fecha:</span><span>{{ ($invoice->issued_at ?? now())->format('d/m/Y H:i') }}</span></div>
    @if ($invoice->contact)
        <div class="row"><span>Cliente:</span><span>{{ $invoice->contact->name }}</span></div>
    @endif

    <div class="divider"></div>

    <div class="items">
        @foreach ($invoice->items as $item)
            <div class="line">
                <div class="qty">{{ number_format($item->quantity, 0) }}x</div>
                <div class="name">{{ $item->description }}</div>
                <div class="total">${{ number_format($item->total, 0, ',', '.') }}</div>
            </div>
        @endforeach
    </div>

    <div class="divider"></div>

    <div class="totals">
        <div class="row"><span class="label">Subtotal</span><span>${{ number_format($invoice->subtotal, 0, ',', '.') }}</span></div>
        @if ((float) $invoice->discount_total > 0)
            <div class="row"><span class="label">Descuento</span><span>-${{ number_format($invoice->discount_total, 0, ',', '.') }}</span></div>
        @endif
        @if ((float) $invoice->tax_total > 0)
            <div class="row"><span class="label">IVA</span><span>${{ number_format($invoice->tax_total, 0, ',', '.') }}</span></div>
        @endif
        <div class="row grand-total"><span>TOTAL</span><span>${{ number_format($invoice->grand_total, 0, ',', '.') }}</span></div>
    </div>

    @if ($invoice->payments->isNotEmpty())
        <div class="divider"></div>
        <div class="payment">
            @foreach ($invoice->payments as $payment)
                <div class="row"><span class="label">Pago {{ $payment->payment_method->getLabel() }}</span><span>${{ number_format($payment->amount, 0, ',', '.') }}</span></div>
                @if ($payment->cash_received !== null)
                    <div class="row"><span class="label">Recibido</span><span>${{ number_format($payment->cash_received, 0, ',', '.') }}</span></div>
                @endif
                @if ($payment->change_due !== null)
                    <div class="row"><span class="label">Cambio</span><span>${{ number_format($payment->change_due, 0, ',', '.') }}</span></div>
                @endif
            @endforeach
        </div>
    @endif

    @if ($invoice->cufe)
        <div class="footer">
            CUFE: {{ $invoice->cufe }}<br>
            Valide en la DIAN
        </div>
    @endif

    <div class="footer">
        © {{ now()->format('Y') }} {{ $tenant?->name ?? 'Taller' }}<br>
        Documento electrónico generado por el sistema.
    </div>
</body>
</html>