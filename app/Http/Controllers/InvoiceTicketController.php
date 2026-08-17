<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Modules\Facturacion\Models\Invoice;
use Illuminate\View\View;

class InvoiceTicketController
{
    public function __invoke(Invoice $invoice): View
    {
        abort_if($invoice->tenant_id !== auth()->user()->tenant_id, 403);

        $tenant = Tenant::find($invoice->tenant_id);

        return view('facturacion.ticket-pos', compact('invoice', 'tenant'));
    }
}
