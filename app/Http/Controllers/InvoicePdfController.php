<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\InvoicePdfService;
use App\Services\TenantManager;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController
{
    public function __invoke(Invoice $invoice): Response
    {
        $this->ensureTenantOwnership($invoice);

        return app(InvoicePdfService::class)->download($invoice);
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
