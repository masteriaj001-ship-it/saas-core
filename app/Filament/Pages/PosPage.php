<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Models\Item;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\InvoiceCreationService;
use App\Modules\Shared\Services\Print\EscPosService;
use App\Modules\Shared\Services\Print\PrinterSettingsResolver;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class PosPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'Facturación';

    protected static ?string $title = 'Punto de Venta';

    protected static ?string $slug = 'pos';

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.pos';

    public function getMaxContentWidth(): ?string
    {
        return null;
    }

    public array $cart = [];

    public string $search = '';

    public ?string $selectedCategory = null;

    public string $paymentMethod = 'cash';

    public float $amountReceived = 0;

    public bool $showPaymentModal = false;

    public bool $showTicketModal = false;

    public bool $showHistory = false;

    public ?string $lastInvoiceId = null;

    public function mount(): void
    {
        $this->amountReceived = 0;
    }

    public function getItemsProperty()
    {
        $query = Item::query()
            ->where('stock', '>', 0)
            ->orderBy('name');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('sku', 'ilike', "%{$this->search}%");
            });
        }

        if ($this->selectedCategory !== null) {
            $query->where('item_type', $this->selectedCategory);
        }

        return $query->get();
    }

    public function getCategoriesProperty(): array
    {
        $types = Item::where('stock', '>', 0)
            ->select('item_type', DB::raw('count(*) as total'))
            ->groupBy('item_type')
            ->pluck('total', 'item_type')
            ->toArray();

        $labels = [
            'spare' => 'Repuestos',
            'product' => 'Productos',
            'service' => 'Servicios',
            'raw_material' => 'Materia Prima',
        ];

        $categories = [];
        foreach ($types as $type => $count) {
            $categories[] = [
                'key' => $type,
                'label' => $labels[$type] ?? $type,
                'total' => $count,
            ];
        }

        return $categories;
    }

    public function addItem(string $itemId): void
    {
        $item = Item::findOrFail($itemId);

        if (! $item->hasStock(1)) {
            Notification::make()
                ->title(__('Sin stock disponible'))
                ->body(__('El artículo :name no tiene stock suficiente.', ['name' => $item->name]))
                ->warning()
                ->send();

            return;
        }

        $existingIndex = null;
        foreach ($this->cart as $index => $cartItem) {
            if ($cartItem['item_id'] === $itemId) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            $newQty = $this->cart[$existingIndex]['quantity'] + 1;
            if (! $item->hasStock($newQty)) {
                Notification::make()
                    ->title(__('Stock insuficiente'))
                    ->body(__('Solo hay :stock unidades de :name.', ['stock' => $item->stock, 'name' => $item->name]))
                    ->warning()
                    ->send();

                return;
            }
            $this->cart[$existingIndex]['quantity'] = $newQty;
            $this->cart[$existingIndex]['subtotal'] = round($newQty * $this->cart[$existingIndex]['price'], 2);
        } else {
            $this->cart[] = [
                'item_id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'price' => (float) $item->price,
                'quantity' => 1,
                'subtotal' => (float) $item->price,
            ];
        }

        $this->amountReceived = $this->getCartSubtotalProperty();
    }

    public function removeItem(int $index): void
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
            $this->amountReceived = $this->getCartSubtotalProperty();
        }
    }

    public function updateQuantity(int $index, int $quantity): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        if ($quantity <= 0) {
            $this->removeItem($index);

            return;
        }

        $item = Item::find($this->cart[$index]['item_id']);
        if ($item && ! $item->hasStock($quantity)) {
            Notification::make()
                ->title(__('Stock insuficiente'))
                ->body(__('Solo hay :stock unidades disponibles.', ['stock' => $item->stock]))
                ->warning()
                ->send();

            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['subtotal'] = round($quantity * $this->cart[$index]['price'], 2);
        $this->amountReceived = $this->getCartSubtotalProperty();
    }

    public function getCartSubtotalProperty(): float
    {
        return round(collect($this->cart)->sum('subtotal'), 2);
    }

    public function getChangeProperty(): float
    {
        if ($this->paymentMethod !== 'cash' || $this->amountReceived <= 0) {
            return 0;
        }

        return round(max(0, $this->amountReceived - $this->getCartSubtotalProperty()), 2);
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;
        if ($method !== 'cash') {
            $this->amountReceived = $this->getCartSubtotalProperty();
        }
    }

    public function openPayment(): void
    {
        if (empty($this->cart)) {
            Notification::make()
                ->title(__('Carrito vacío'))
                ->body(__('Agrega artículos antes de cobrar.'))
                ->warning()
                ->send();

            return;
        }

        $this->amountReceived = $this->getCartSubtotalProperty();
        $this->showPaymentModal = true;
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            return;
        }

        $subtotal = $this->getCartSubtotalProperty();
        $paymentData = [
            'method' => $this->paymentMethod,
            'amount' => $subtotal,
        ];

        if ($this->paymentMethod === 'cash') {
            if ($this->amountReceived < $subtotal) {
                Notification::make()
                    ->title(__('Monto insuficiente'))
                    ->body(__('El monto recibido es menor al total.'))
                    ->warning()
                    ->send();

                return;
            }
            $paymentData['cash_received'] = $this->amountReceived;
        }

        try {
            $tenant = Filament::getTenant();

            $invoice = DB::transaction(function () use ($tenant, $paymentData) {
                $service = app(InvoiceCreationService::class);

                return $service->create($tenant, InvoiceDocumentTypeEnum::Pos, [
                    'items' => collect($this->cart)->map(fn ($item) => [
                        'description' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'discount' => 0,
                    ])->toArray(),
                    'payment' => $paymentData,
                    'notes' => 'POS - '.auth()->user()->name,
                ]);
            });

            $this->lastInvoiceId = $invoice->id;
            $this->cart = [];
            $this->showPaymentModal = false;
            $this->showTicketModal = true;
            $this->paymentMethod = 'cash';
            $this->amountReceived = 0;
            $this->search = '';
            $this->selectedCategory = null;

            Notification::make()
                ->title(__('Venta registrada'))
                ->body(__('Documento :number creado exitosamente.', ['number' => $invoice->document_number]))
                ->success()
                ->send();

        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('Error al procesar'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleHistory(): void
    {
        $this->showHistory = ! $this->showHistory;
    }

    public function getRecentSalesProperty()
    {
        $tenant = Filament::getTenant();

        return Invoice::query()
            ->where('document_type', InvoiceDocumentTypeEnum::Pos)
            ->where('status', 'paid')
            ->latest('issued_at')
            ->limit(20)
            ->get();
    }

    public function getLastInvoiceGrandTotalProperty(): ?string
    {
        if ($this->lastInvoiceId === null) {
            return null;
        }

        $tenant = Filament::getTenant();

        $grandTotal = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $this->lastInvoiceId)
            ->value('grand_total');

        return $grandTotal === null ? null : number_format((float) $grandTotal, 0, ',', '.');
    }

    public function setCategory(?string $category): void
    {
        $this->selectedCategory = $this->selectedCategory === $category ? null : $category;
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->amountReceived = 0;
    }

    public function closeTicketModal(): void
    {
        $this->showTicketModal = false;
        $this->lastInvoiceId = null;
    }

    public function printInvoice(): void
    {
        if ($this->lastInvoiceId === null) {
            return;
        }

        $tenant = Filament::getTenant();
        $resolver = new PrinterSettingsResolver($tenant);

        if (! $resolver->usesEscPos()) {
            Notification::make()
                ->title(__('Usa la vista del ticket'))
                ->body(__('Este taller usa impresión por navegador: abre "Ver ticket" e imprime desde ahí.'))
                ->info()
                ->send();

            return;
        }

        $invoice = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $this->lastInvoiceId)
            ->first();

        if ($invoice === null) {
            return;
        }

        $service = new EscPosService;

        $ok = $service->send(
            $service->build($invoice),
            $resolver->host(),
            $resolver->port(),
        );

        if ($resolver->cashDrawerEnabled()) {
            $service->send(
                $service->cashDrawerPulse($resolver->cashDrawerChannel()),
                $resolver->host(),
                $resolver->port(),
            );
        }

        Notification::make()
            ->title($ok ? __('Ticket enviado a impresora') : __('Impresora no alcanzable'))
            ->body($ok ? __('Documento :number enviado por TCP.', ['number' => $invoice->document_number]) : __('Vuelve a intentarlo o usa "Ver ticket".'))
            ->{$ok ? 'success' : 'warning'}()
            ->send();
    }
}
