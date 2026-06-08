<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\InvoicePdfService;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController
{
    public function __invoke(Invoice $invoice): Response
    {
        abort_if($invoice->tenant_id !== auth()->user()->tenant_id, 403);

        return app(InvoicePdfService::class)->download($invoice);
    }
}
