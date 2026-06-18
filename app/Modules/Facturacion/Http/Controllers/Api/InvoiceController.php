<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Http\Controllers\Api;

use App\Models\Tenant;
use App\Modules\Facturacion\Http\Requests\CancelInvoiceRequest;
use App\Modules\Facturacion\Http\Requests\StoreInvoiceRequest;
use App\Modules\Facturacion\Http\Requests\UpdateInvoiceRequest;
use App\Modules\Facturacion\Http\Resources\InvoiceResource;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\InvoiceCreationService;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceCreationService $creationService,
        private readonly TenantManager $tenantManager,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->document_type, fn ($q, $v) => $q->where('document_type', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->with(['items', 'contact'])
            ->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->per_page ?? 15), 100));

        return InvoiceResource::collection($invoices)->response();
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        $tenant = Tenant::findOrFail($tenantId);
        $documentType = $tenant->documentTypeForRegimen();

        $invoice = $this->creationService->create($tenant, $documentType, $request->validated());

        return new InvoiceResource($invoice)->response($request);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['items', 'contact']);

        return new InvoiceResource($invoice)->response();
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        if ($request->has('notes')) {
            $invoice->update(['notes' => $request->notes]);
        }

        if ($request->has('items')) {
            $invoice->items()->delete();

            foreach ($request->items as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax_rate' => $invoice->tax_total > 0 ? 19 : 0,
                    'tax_amount' => 0,
                    'subtotal' => (float) $item['quantity'] * (float) $item['unit_price'],
                    'total' => (float) $item['quantity'] * (float) $item['unit_price'],
                ]);
            }

            $invoice->refresh();
        }

        $invoice->load(['items', 'contact']);

        return new InvoiceResource($invoice)->response();
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice->update([
            'status' => 'cancelled',
            'notes' => trim(($invoice->notes ?? '')."\nCancelación: ".$request->reason),
        ]);

        $invoice->load(['items', 'contact']);

        return new InvoiceResource($invoice)->response();
    }
}
