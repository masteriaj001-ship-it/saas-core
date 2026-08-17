<?php

declare(strict_types=1);

namespace App\Modules\Shared\Services\Print;

use App\Modules\Facturacion\Models\Invoice;
use Illuminate\Support\Facades\Log;

final class EscPosService
{
    private const int DEFAULT_TIMEOUT = 3;

    public function build(Invoice $invoice): string
    {
        $payload = (new TicketRenderer)->render($invoice);

        $lines = [
            "\x1d\x40", // GS @ — initialize printer
            $payload['document_number'],
            str_repeat('-', 32),
        ];

        foreach ($payload['items'] as $item) {
            $lines[] = sprintf(
                '%-2s %-18s %s',
                $item['quantity'],
                mb_substr($item['description'], 0, 18),
                number_format($item['total'], 0, ',', '.'),
            );
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = sprintf('Subtotal  %s', number_format($payload['subtotal'], 0, ',', '.'));
        $lines[] = sprintf('IVA       %s', number_format($payload['tax_total'], 0, ',', '.'));
        $lines[] = sprintf('TOTAL     %s', number_format($payload['grand_total'], 0, ',', '.'));

        return $this->withCut(implode("\n", $lines));
    }

    public function withCut(string $data): string
    {
        return $data."\n\x1d\x56\x01"; // GS V — full cut
    }

    public function cashDrawerPulse(int $channel = 2): string
    {
        return "\x1bp".chr($channel)."\x19\x19"; // ESC p m t1 t2 — pulse draw kick
    }

    public function send(string $data, string $host, int $port = 9100): bool
    {
        try {
            $socket = @stream_socket_client(
                "tcp://{$host}:{$port}",
                $errno,
                $errstr,
                self::DEFAULT_TIMEOUT,
            );

            if ($socket === false) {
                Log::warning("Impresora ESC/POS inalcanzable: {$errstr} ({$errno})");

                return false;
            }

            $written = fwrite($socket, $data);
            fclose($socket);

            if ($written === false) {
                Log::warning('No se pudo escribir el ticket en la impresora ESC/POS.');

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Error enviando ticket ESC/POS: '.$e->getMessage());

            return false;
        }
    }
}