<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Shared\Services\Print\EscPosService;
use App\Modules\Shared\Services\Print\PrinterSettingsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosPrintController
{
    public function __invoke(Request $request): JsonResponse
    {
        $invoice = Invoice::find($request->input('invoice_id'));

        abort_if($invoice === null, 404);
        abort_if($invoice->tenant_id !== auth()->user()->tenant_id, 403);

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
