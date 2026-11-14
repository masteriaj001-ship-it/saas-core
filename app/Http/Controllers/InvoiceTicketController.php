<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Modules\Facturacion\Models\Invoice;
use App\Services\TenantManager;
use Illuminate\View\View;

class InvoiceTicketController
{
    public function __invoke(Invoice $invoice): View
    {
        $this->ensureTenantOwnership($invoice);

        $tenant = Tenant::find($invoice->tenant_id);

        return view('facturacion.ticket-pos', compact('invoice', 'tenant'));
    }

    /**
     * Defensa en profundidad: el recurso debe pertenecer al tenant del contexto
     * establecido por SetRequestTenantContext (no al tenant_id del usuario).
     */
    private function ensureTenantOwnership(Invoice $invoice): void
    {
        $tenantId = app(TenantManager::class)->getCurrentTenantId();

        abort_if($tenantId === null, 403, 'Tenant context is not available.');
        abort_unless($invoice->tenant_id === $tenantId, 404);
    }
}
