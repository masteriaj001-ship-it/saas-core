<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Shared\Services\Print\EscPosService;
use App\Modules\Shared\Services\Print\PrinterSettingsResolver;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PosPrintController
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = app(TenantManager::class)->getCurrentTenantId();

        abort_if($tenantId === null, 403, 'Tenant context is not available.');

        $invoiceId = $request->input('invoice_id');

        abort_unless(is_string($invoiceId) && Str::isUuid($invoiceId), 404);

        $invoice = Invoice::find($invoiceId);

        abort_if($invoice === null, 404);
        abort_unless($invoice->tenant_id === $tenantId, 404);

        $tenant = Tenant::find($invoice->tenant_id);
        $resolver = new PrinterSettingsResolver($tenant);

        if ($resolver->usesEscPos()) {
            $ok = (new EscPosService)->send(
                (new EscPosService)->build($invoice),
                $resolver->host(),
                $resolver->port(),
            );

            if ($resolver->cashDrawerEnabled()) {
                (new EscPosService)->send(
                    (new EscPosService)->cashDrawerPulse($resolver->cashDrawerChannel()),
                    $resolver->host(),
                    $resolver->port(),
                );
            }

            if (! $ok) {
                return response()->json(['ok' => false, 'error' => 'printer_unreachable'], 502);
            }

            return response()->json(['ok' => true, 'driver' => 'esc_pos']);
        }

        return response()->json(['ok' => true, 'driver' => 'window_print']);
    }
}
