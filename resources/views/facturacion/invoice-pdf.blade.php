<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $invoice->document_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #333;
            margin: 40px;
        }
        .header {
            border-bottom: 2px solid #1a56db;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 18pt;
            color: #1a56db;
            margin: 0;
        }
        .header .document-number {
            font-size: 16pt;
            font-weight: bold;
            color: #111;
            text-align: right;
        }
        .info-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .info-table td {
            padding: 4px 8px;
            font-size: 9pt;
        }
        .info-table .label {
            font-weight: bold;
            color: #666;
            width: 120px;
        }
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1a56db;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background: #1a56db;
            color: #fff;
            padding: 8px 6px;
            font-size: 9pt;
            text-align: left;
        }
        .items-table td {
            padding: 6px;
            font-size: 9pt;
            border-bottom: 1px solid #eee;
        }
        .items-table .num {
            text-align: center;
            width: 30px;
        }
        .items-table .qty {
            text-align: center;
        }
        .items-table .price {
            text-align: right;
        }
        .items-table .total-col {
            text-align: right;
            font-weight: bold;
        }
        .totals {
            width: 300px;
            margin-left: auto;
            margin-bottom: 30px;
        }
        .totals td {
            padding: 4px 8px;
            font-size: 10pt;
        }
        .totals .total-label {
            text-align: right;
            font-weight: bold;
        }
        .totals .total-value {
            text-align: right;
            width: 120px;
        }
        .totals .grand-total {
            font-size: 13pt;
            font-weight: bold;
            color: #1a56db;
            border-top: 2px solid #1a56db;
        }
        .notes {
            border-top: 1px solid #ddd;
            padding-top: 15px;
            font-size: 9pt;
            color: #666;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 40px;
            right: 40px;
            text-align: center;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">
                    <h1>{{ $tenant?->name ?? 'Taller' }}</h1>
                    <p style="margin: 2px 0; font-size: 9pt;">
                        NIT: {{ $invoice->contact?->tax_id ?? '—' }}<br>
                        Tel: {{ $invoice->contact?->phone ?? '—' }}
                    </p>
                </td>
                <td style="width: 50%; text-align: right;">
                    <div class="document-number">{{ $invoice->document_number }}</div>
                    <p style="margin: 2px 0; font-size: 9pt;">
                        Fecha: {{ $invoice->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}<br>
                        Vence: {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Cliente</div>
    <table class="info-table">
        <tr>
            <td class="label">Nombre:</td>
            <td>{{ $invoice->contact?->name ?? '—' }}</td>
            <td class="label">Documento:</td>
            <td>{{ $invoice->contact?->document_type?->value ?? '—' }} {{ $invoice->contact?->document_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Ciudad:</td>
            <td>{{ $invoice->contact->city ?? '—' }}</td>
            <td class="label">Teléfono:</td>
            <td>{{ $invoice->contact->phone ?? '—' }}</td>
        </tr>
        @if($invoice->workOrder)
        <tr>
            <td class="label">OT relacionada:</td>
            <td colspan="3">{{ $invoice->workOrder->code }} — {{ $invoice->workOrder->title }}</td>
        </tr>
        @endif
    </table>

    <div class="section-title">Detalle</div>
    <table class="items-table">
        <thead>
            <tr>
                <th class="num">#</th>
                <th style="width: 40%;">Descripción</th>
                <th class="qty">Cant</th>
                <th class="price">P. Unit</th>
                @if($invoice->tax_total > 0)
                <th class="price">IVA</th>
                @endif
                <th class="total-col">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $index => $item)
            <tr>
                <td class="num">{{ $index + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="qty">{{ number_format($item->quantity, 2) }}</td>
                <td class="price">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                @if($invoice->tax_total > 0)
                <td class="price">{{ number_format($item->tax_rate, 0) }}%</td>
                @endif
                <td class="total-col">${{ number_format($item->total, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ $invoice->tax_total > 0 ? 6 : 5 }}" style="text-align: center; color: #999;">Sin ítems</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="total-label">Subtotal:</td>
            <td class="total-value">${{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
        </tr>
        @if($invoice->discount_total > 0)
        <tr>
            <td class="total-label">Descuento:</td>
            <td class="total-value">-${{ number_format($invoice->discount_total, 0, ',', '.') }}</td>
        </tr>
        @endif
        @if($invoice->tax_total > 0)
        <tr>
            <td class="total-label">IVA:</td>
            <td class="total-value">${{ number_format($invoice->tax_total, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="grand-total">
            <td class="total-label">TOTAL:</td>
            <td class="total-value">${{ number_format($invoice->grand_total, 0, ',', '.') }}</td>
        </tr>
    </table>

    @if($invoice->notes)
    <div class="notes">
        <strong>Notas:</strong><br>
        {{ $invoice->notes }}
    </div>
    @endif

    <div class="footer">
        Documento generado electrónicamente — {{ $invoice->document_number }} — {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
