@php use Filament\Facades\Filament; @endphp

<div>
<style>
    .pos-kiosk { position: fixed; inset: 0; z-index: 9999; display: flex; flex-direction: column; background: #030712; color: #fff; overflow: hidden; font-family: 'Inter', system-ui, sans-serif; }
    .pos-kiosk * { box-sizing: border-box; margin: 0; padding: 0; }
    .pos-topbar { display: flex; align-items: center; justify-content: space-between; padding: 12px 24px; background: #111827; border-bottom: 1px solid #1f2937; flex-shrink: 0; }
    .pos-topbar h1 { font-size: 18px; font-weight: 700; letter-spacing: 0.05em; }
    .pos-topbar .search { width: 256px; padding: 8px 12px 8px 36px; background: #1f2937; border: 1px solid #374151; border-radius: 8px; color: #fff; font-size: 14px; outline: none; }
    .pos-topbar .search:focus { border-color: #f59e0b; }
    .pos-topbar .btn { padding: 8px 12px; font-size: 13px; font-weight: 500; border-radius: 8px; border: none; cursor: pointer; transition: all 0.15s; }
    .pos-topbar .btn-ghost { background: #1f2937; color: #d1d5db; }
    .pos-topbar .btn-ghost:hover { background: #374151; }
    .pos-topbar .btn-active { background: #f59e0b; color: #000; }
    .pos-main { display: flex; flex: 1; overflow: hidden; }
    .pos-categories { width: 192px; background: #111827; border-right: 1px solid #1f2937; display: flex; flex-direction: column; flex-shrink: 0; }
    .pos-categories .cat-header { padding: 12px; border-bottom: 1px solid #1f2937; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; }
    .pos-categories .cat-list { flex: 1; overflow-y: auto; padding: 8px; }
    .pos-cat-btn { width: 100%; text-align: left; padding: 10px 12px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 1px solid transparent; cursor: pointer; background: transparent; color: #d1d5db; transition: all 0.15s; }
    .pos-cat-btn:hover { background: #1f2937; }
    .pos-cat-btn.active { background: rgba(245,158,11,0.2); color: #f59e0b; border-color: rgba(245,158,11,0.3); }
    .pos-catalog { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .pos-catalog-header { padding: 16px; border-bottom: 1px solid #1f2937; flex-shrink: 0; display: flex; justify-content: space-between; }
    .pos-catalog-header h2 { font-size: 14px; font-weight: 600; color: #9ca3af; }
    .pos-catalog-header span { font-size: 12px; color: #6b7280; }
    .pos-items { flex: 1; overflow-y: auto; padding: 16px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; align-content: start; }
    .pos-item { background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 16px; cursor: pointer; transition: all 0.15s; text-align: left; }
    .pos-item:hover { border-color: rgba(245,158,11,0.5); background: #1f2937; }
    .pos-item .item-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
    .pos-item .sku { font-size: 10px; padding: 2px 6px; background: #1f2937; border: 1px solid #374151; border-radius: 4px; color: #9ca3af; }
    .pos-item .stock { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; }
    .pos-item .stock-ok { background: rgba(16,185,129,0.2); color: #34d399; }
    .pos-item .stock-low { background: rgba(239,68,68,0.2); color: #f87171; }
    .pos-item .name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .pos-item .price { font-size: 18px; font-weight: 700; color: #f59e0b; margin-top: 12px; }
    .pos-item .unit { font-size: 10px; color: #6b7280; text-transform: uppercase; }
    .pos-ticket { width: 384px; background: #111827; border-left: 1px solid #1f2937; display: flex; flex-direction: column; flex-shrink: 0; }
    .pos-ticket-header { padding: 16px; border-bottom: 1px solid #1f2937; flex-shrink: 0; display: flex; justify-content: space-between; }
    .pos-ticket-header h2 { font-size: 14px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; }
    .pos-ticket-header .clear { font-size: 12px; color: #f87171; background: none; border: none; cursor: pointer; }
    .pos-ticket-items { flex: 1; overflow-y: auto; }
    .pos-cart-item { padding: 12px; border-bottom: 1px solid #1f2937; }
    .pos-cart-item .item-name { font-size: 14px; font-weight: 500; color: #fff; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pos-cart-item .item-sku { font-size: 10px; color: #6b7280; }
    .pos-cart-item .item-controls { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
    .pos-qty-btn { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 6px; background: #1f2937; color: #9ca3af; border: none; cursor: pointer; font-size: 14px; font-weight: 700; }
    .pos-qty-btn:hover { background: #374151; color: #fff; }
    .pos-qty { width: 32px; text-align: center; font-size: 14px; font-weight: 600; color: #fff; }
    .pos-item-total { font-size: 14px; font-weight: 700; color: #f59e0b; }
    .pos-ticket-footer { border-top: 1px solid #1f2937; padding: 16px; flex-shrink: 0; }
    .pos-total-row { display: flex; justify-content: space-between; font-size: 14px; color: #9ca3af; margin-bottom: 4px; }
    .pos-grand-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; border-top: 1px solid #1f2937; padding-top: 8px; margin-top: 4px; }
    .pos-grand-total .amount { color: #f59e0b; }
    .pos-checkout-btn { width: 100%; padding: 12px; border-radius: 12px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border: none; cursor: pointer; margin-top: 16px; transition: all 0.15s; }
    .pos-checkout-btn.enabled { background: #f59e0b; color: #000; }
    .pos-checkout-btn.enabled:hover { background: #fbbf24; box-shadow: 0 0 20px rgba(245,158,11,0.3); }
    .pos-checkout-btn.disabled { background: #1f2937; color: #4b5563; cursor: not-allowed; }
    .pos-shortcuts { margin-top: 12px; display: flex; justify-content: center; gap: 16px; font-size: 10px; color: #4b5563; }
    .pos-modal-overlay { position: fixed; inset: 0; z-index: 10000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); }
    .pos-modal { background: #111827; border: 1px solid #374151; border-radius: 16px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); width: 100%; max-width: 448px; overflow: hidden; }
    .pos-modal-header { padding: 24px; border-bottom: 1px solid #1f2937; text-align: center; }
    .pos-modal-header h3 { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 16px; }
    .pos-modal-header .total-label { font-size: 14px; color: #9ca3af; margin-bottom: 4px; }
    .pos-modal-header .total-amount { font-size: 36px; font-weight: 700; color: #f59e0b; }
    .pos-modal-body { padding: 24px; }
    .pos-methods-label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
    .pos-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 24px; }
    .pos-method-btn { padding: 12px; border-radius: 12px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.15s; background: #1f2937; color: #d1d5db; }
    .pos-method-btn.active { background: #f59e0b; color: #000; box-shadow: 0 0 14px rgba(245,158,11,0.3); }
    .pos-method-btn:hover:not(.active) { background: #374151; }
    .pos-cash-input { position: relative; margin-bottom: 16px; }
    .pos-cash-input .prefix { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-size: 24px; font-weight: 700; color: #9ca3af; }
    .pos-cash-input input { width: 100%; padding: 16px 16px 16px 40px; background: #1f2937; border: 1px solid #374151; border-radius: 12px; font-size: 30px; font-weight: 700; color: #fff; text-align: right; outline: none; }
    .pos-cash-input input:focus { border-color: #f59e0b; }
    .pos-quick-amounts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 12px; }
    .pos-quick-btn { padding: 8px; border-radius: 8px; font-size: 12px; font-weight: 600; background: #1f2937; color: #d1d5db; border: none; cursor: pointer; }
    .pos-quick-btn:hover { background: #374151; }
    .pos-change { margin-top: 16px; padding: 12px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px; text-align: center; }
    .pos-change .label { font-size: 12px; color: #34d399; margin-bottom: 4px; }
    .pos-change .amount { font-size: 24px; font-weight: 700; color: #34d399; }
    .pos-confirm-btn { width: 100%; padding: 16px; border-radius: 12px; font-size: 16px; font-weight: 700; text-transform: uppercase; border: none; cursor: pointer; transition: all 0.15s; }
    .pos-confirm-btn.ready { background: #10b981; color: #fff; }
    .pos-confirm-btn.ready:hover { background: #34d399; box-shadow: 0 0 20px rgba(16,185,129,0.3); }
    .pos-confirm-btn.not-ready { background: #1f2937; color: #4b5563; cursor: not-allowed; }
    .pos-cancel-btn { width: 100%; margin-top: 8px; padding: 8px; font-size: 14px; color: #6b7280; background: none; border: none; cursor: pointer; }
    .pos-cancel-btn:hover { color: #fff; }
    .pos-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #4b5563; padding: 16px; }
    .pos-empty p { font-size: 12px; text-align: center; }
    .pos-history-overlay { position: fixed; inset: 0; z-index: 10000; display: flex; justify-content: flex-end; background: rgba(0,0,0,0.4); }
    .pos-history-panel { width: 384px; background: #111827; border-left: 1px solid #1f2937; height: 100%; overflow-y: auto; }
    .pos-history-header { padding: 16px; border-bottom: 1px solid #1f2937; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: #111827; z-index: 10; }
    .pos-history-header h3 { font-size: 14px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 0.05em; }
    .pos-history-item { padding: 16px; border-bottom: 1px solid #1f2937; }
    .pos-history-item .doc-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
    .pos-history-item .doc-num { font-size: 14px; font-weight: 600; color: #fff; }
    .pos-history-item .doc-total { font-size: 14px; font-weight: 700; color: #f59e0b; }
    .pos-history-item .doc-meta { display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; }
    .pos-history-item .doc-link { color: #f59e0b; text-decoration: none; }
    .pos-history-item .doc-link:hover { color: #fbbf24; }
    .pos-empty-state { padding: 32px; text-align: center; color: #4b5563; }
</style>

<div
    class="pos-kiosk"
    x-data="posKiosk()"
    x-on:keydown.f2.window.prevent="$wire.setPaymentMethod('cash'); $wire.openPayment()"
    x-on:keydown.f4.window.prevent="$wire.setPaymentMethod('card'); $wire.openPayment()"
    x-on:keydown.f5.window.prevent="$wire.toggleHistory()"
    x-on:keydown.f10.window.prevent="$wire.checkout()"
    x-on:keydown.escape.window="if($wire.showPaymentModal) $wire.set('showPaymentModal', false)"
>
    <div class="pos-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <h1>PUNTO DE VENTA</h1>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar artículo... (F3)" class="search" />
            <button wire:click="toggleHistory" class="btn {{ $showHistory ? 'btn-active' : 'btn-ghost' }}">Historial (F5)</button>
            <a href="{{ route('filament.admin.pages.dashboard', ['tenant' => Filament::getTenant()->slug]) }}" class="btn btn-ghost" style="text-decoration:none;">Salir</a>
        </div>
    </div>

    <div class="pos-main">
        <div class="pos-categories">
            <div class="cat-header">Categorías</div>
            <div class="cat-list">
                <button wire:click="setCategory(null)" class="pos-cat-btn {{ is_null($selectedCategory) ? 'active' : '' }}">Todos</button>
                @foreach($this->categories as $cat)
                    <button wire:click="setCategory('{{ $cat['key'] }}')" class="pos-cat-btn {{ $selectedCategory === $cat['key'] ? 'active' : '' }}">
                        <span style="display:flex;justify-content:space-between;">
                            <span>{{ $cat['label'] }}</span>
                            <span style="font-size:12px;color:#6b7280;">{{ $cat['total'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="pos-catalog">
            <div class="pos-catalog-header">
                <h2>{{ $selectedCategory ? ucfirst($selectedCategory) : 'Todos los artículos' }}</h2>
                <span>{{ $this->items->count() }} artículos</span>
            </div>
            <div class="pos-items">
                @forelse($this->items as $item)
                    <button wire:click="addItem('{{ $item->id }}')" class="pos-item">
                        <div class="item-header">
                            <span class="sku">{{ $item->sku }}</span>
                            <span class="stock {{ $item->stock > $item->min_stock ? 'stock-ok' : 'stock-low' }}">{{ $item->stock }}</span>
                        </div>
                        <div class="name">{{ $item->name }}</div>
                        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:12px;">
                            <span class="price">${{ number_format($item->price, 0, ',', '.') }}</span>
                            <span class="unit">{{ $item->unit }}</span>
                        </div>
                    </button>
                @empty
                    <div class="pos-empty">
                        <p>No se encontraron artículos</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="pos-ticket">
            <div class="pos-ticket-header">
                <h2>Ticket</h2>
                @if(count($cart) > 0)
                    <button wire:click="clearCart" class="clear">Vaciar</button>
                @endif
            </div>
            <div style="padding:0 16px 4px;font-size:12px;color:#6b7280;">
                {{ count($cart) }} artículo(s) · ${{ number_format($this->cartSubtotal, 0, ',', '.') }}
            </div>

            <div class="pos-ticket-items">
                @if(empty($cart))
                    <div class="pos-empty">
                        <p>Carrito vacío<br>Agrega artículos desde el catálogo</p>
                    </div>
                @else
                    @foreach($cart as $index => $cartItem)
                        <div class="pos-cart-item">
                            <div style="display:flex;justify-content:space-between;align-items:start;">
                                <div style="flex:1;min-width:0;">
                                    <div class="item-name">{{ $cartItem['name'] }}</div>
                                    <div class="item-sku">{{ $cartItem['sku'] }} · ${{ number_format($cartItem['price'], 0, ',', '.') }}</div>
                                </div>
                                <button wire:click="removeItem({{ $index }})" style="background:none;border:none;color:#6b7280;cursor:pointer;font-size:16px;padding:4px;">&times;</button>
                            </div>
                            <div class="item-controls">
                                <div style="display:flex;align-items:center;gap:4px;">
                                    <button wire:click="updateQuantity({{ $index }}, {{ $cartItem['quantity'] - 1 }})" class="pos-qty-btn">&minus;</button>
                                    <span class="pos-qty">{{ $cartItem['quantity'] }}</span>
                                    <button wire:click="updateQuantity({{ $index }}, {{ $cartItem['quantity'] + 1 }})" class="pos-qty-btn">+</button>
                                </div>
                                <span class="pos-item-total">${{ number_format($cartItem['subtotal'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="pos-ticket-footer">
                <div class="pos-total-row">
                    <span>Subtotal</span>
                    <span style="font-weight:600;">${{ number_format($this->cartSubtotal, 0, ',', '.') }}</span>
                </div>
                <div class="pos-grand-total">
                    <span>TOTAL</span>
                    <span class="amount">${{ number_format($this->cartSubtotal, 0, ',', '.') }}</span>
                </div>
                <button wire:click="openPayment" class="pos-checkout-btn {{ count($cart) > 0 ? 'enabled' : 'disabled' }}">
                    Cobrar (F2/F4)
                </button>
                <div class="pos-shortcuts">
                    <span>F2 Efectivo</span>
                    <span>F4 Tarjeta</span>
                    <span>F5 Historial</span>
                    <span>F10 Cobrar</span>
                </div>
            </div>
        </div>
    </div>

    @if($showPaymentModal)
        <div class="pos-modal-overlay" x-on:keydown.escape.window="$wire.set('showPaymentModal', false)">
            <div class="pos-modal">
                <div class="pos-modal-header">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h3 style="margin:0;">Cobrar</h3>
                        <button wire:click="set('showPaymentModal', false)" style="background:none;border:none;color:#6b7280;cursor:pointer;font-size:20px;">&times;</button>
                    </div>
                    <div class="total-label">Total a pagar</div>
                    <div class="total-amount">${{ number_format($this->cartSubtotal, 0, ',', '.') }}</div>
                </div>
                <div class="pos-modal-body">
                    <div class="pos-methods-label">Método de pago</div>
                    <div class="pos-methods">
                        <button wire:click="setPaymentMethod('cash')" class="pos-method-btn {{ $paymentMethod === 'cash' ? 'active' : '' }}">Efectivo</button>
                        <button wire:click="setPaymentMethod('card')" class="pos-method-btn {{ $paymentMethod === 'card' ? 'active' : '' }}">Tarjeta</button>
                        <button wire:click="setPaymentMethod('transfer')" class="pos-method-btn {{ $paymentMethod === 'transfer' ? 'active' : '' }}">Transferencia</button>
                    </div>

                    @if($paymentMethod === 'cash')
                        <div>
                            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:8px;">Monto recibido</label>
                            <div class="pos-cash-input">
                                <span class="prefix">$</span>
                                <input type="number" wire:model.live="amountReceived" min="0" step="100" autofocus />
                            </div>
                            @php
                                $total = $this->cartSubtotal;
                                $amounts = array_unique([$total, ceil($total/1000)*1000, ceil($total/5000)*5000, ceil($total/10000)*10000]);
                            @endphp
                            <div class="pos-quick-amounts">
                                @foreach(array_slice($amounts, 0, 4) as $amt)
                                    <button wire:click="set('amountReceived', {{ $amt }})" class="pos-quick-btn">${{ number_format($amt, 0, ',', '.') }}</button>
                                @endforeach
                            </div>
                            @if($this->change > 0)
                                <div class="pos-change">
                                    <div class="label">Cambio</div>
                                    <div class="amount">${{ number_format($this->change, 0, ',', '.') }}</div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <button wire:click="checkout" class="pos-confirm-btn {{ ($paymentMethod !== 'cash' || $amountReceived >= $this->cartSubtotal) ? 'ready' : 'not-ready' }}" @if($paymentMethod === 'cash' && $amountReceived < $this->cartSubtotal) disabled @endif>
                        Confirmar Pago
                    </button>
                    <button wire:click="set('showPaymentModal', false)" class="pos-cancel-btn">Cancelar (Esc)</button>
                </div>
            </div>
        </div>
    @endif

    @if($showHistory)
        <div class="pos-history-overlay" x-on:keydown.escape.window="$wire.set('showHistory', false)">
            <div class="pos-history-panel">
                <div class="pos-history-header">
                    <h3>Últimas Ventas</h3>
                    <button wire:click="set('showHistory', false)" style="background:none;border:none;color:#6b7280;cursor:pointer;font-size:20px;">&times;</button>
                </div>
                @forelse($this->recentSales as $sale)
                    <div class="pos-history-item">
                        <div class="doc-row">
                            <span class="doc-num">{{ $sale->document_number }}</span>
                            <span class="doc-total">${{ number_format($sale->grand_total, 0, ',', '.') }}</span>
                        </div>
                        <div class="doc-meta">
                            <span>{{ $sale->issued_at?->format('d/m/Y H:i') }}</span>
                            <a href="{{ route('filament.admin.invoices.ticket', ['invoice' => $sale->id]) }}" target="_blank" class="doc-link">Ver ticket &rarr;</a>
                        </div>
                    </div>
                @empty
                    <div class="pos-empty-state">
                        <p>No hay ventas recientes</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>

</div>
