<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Models\Tenant;
use App\Modules\Facturacion\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

final class InvoicePdfService
{
    public function generate(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load(['items', 'contact', 'workOrder']);

        $tenant = Tenant::find($invoice->tenant_id);

        return Pdf::loadView('facturacion.invoice-pdf', compact('invoice', 'tenant'))
            ->setPaper('a4', 'portrait');
    }

    public function download(Invoice $invoice): Response
    {
        return $this->generate($invoice)
            ->download("{$invoice->document_number}.pdf");
    }

    public function stream(Invoice $invoice): Response
    {
        return $this->generate($invoice)
            ->stream("{$invoice->document_number}.pdf");
    }
}
