<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Presupuesto - {{ $workOrder->code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; color: #1a1a1a; padding: 1rem; line-height: 1.5; }
        .card { max-width: 48rem; margin: 2rem auto; background: #fff; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); overflow: hidden; }
        .header { background: #2563eb; color: #fff; padding: 1.5rem; }
        .header h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem; }
        .header p { font-size: 0.875rem; opacity: .9; }
        .body { padding: 1.5rem; }
        .section { margin-bottom: 1.5rem; }
        .section:last-child { margin-bottom: 0; }
        .section h2 { font-size: 0.875rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 0.75rem; }
        .item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; font-size: 0.9375rem; }
        .item:last-child { border-bottom: none; }
        .item-name { flex: 1; }
        .item-qty { color: #6b7280; margin: 0 1rem; white-space: nowrap; }
        .item-price { font-weight: 500; white-space: nowrap; }
        .total { display: flex; justify-content: space-between; padding: 1rem 0 0; margin-top: 0.5rem; border-top: 2px solid #e5e7eb; font-size: 1.125rem; font-weight: 700; }
        .actions { display: flex; gap: 0.75rem; flex-direction: column; margin-top: 2rem; }
        .actions form { flex: 1; }
        .btn { width: 100%; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; text-align: center; transition: opacity .15s; }
        .btn:hover { opacity: .9; }
        .btn-approve { background: #16a34a; color: #fff; }
        .btn-reject { background: #dc2626; color: #fff; }
        .reason-input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9375rem; margin-bottom: 0.75rem; resize: vertical; font-family: inherit; }
        .reason-input:focus { outline: 2px solid #2563eb; outline-offset: -1px; border-color: transparent; }
        .meta { font-size: 0.8125rem; color: #9ca3af; margin-top: 1.5rem; text-align: center; }
        @media (min-width: 640px) { .actions { flex-direction: row; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Presupuesto {{ $workOrder->code }}</h1>
            <p>{{ $workOrder->service_description ?? $workOrder->title }}</p>
        </div>
        <div class="body">
            @if($workOrder->description)
                <div class="section">
                    <h2>Descripción</h2>
                    <p style="font-size:.9375rem;color:#374151;">{{ $workOrder->description }}</p>
                </div>
            @endif

            <div class="section">
                <h2>Detalle del presupuesto</h2>
                @foreach($workOrder->items as $item)
                    <div class="item">
                        <span class="item-name">{{ $item->description }}</span>
                        <span class="item-qty">x{{ $item->quantity }}</span>
                        <span class="item-price">${{ number_format($item->unit_price * $item->quantity, 2) }}</span>
                    </div>
                @endforeach
                <div class="total">
                    <span>Total</span>
                    <span>${{ number_format($workOrder->items->sum(fn($i) => $i->unit_price * $i->quantity), 2) }}</span>
                </div>
            </div>

            <div class="actions">
                <form method="POST" action="{{ route('quote.approval.reject', ['workOrder' => $workOrder]) }}">
                    @csrf
                    <textarea name="reason" class="reason-input" rows="2" placeholder="Motivo de rechazo (opcional)"></textarea>
                    <button type="submit" class="btn btn-reject">Rechazar</button>
                </form>
                <form method="POST" action="{{ route('quote.approval.approve', ['workOrder' => $workOrder]) }}">
                    @csrf
                    <button type="submit" class="btn btn-approve">Aprobar</button>
                </form>
            </div>

            <p class="meta">Este enlace expirará en 7 días.</p>
        </div>
    </div>
</body>
</html>
