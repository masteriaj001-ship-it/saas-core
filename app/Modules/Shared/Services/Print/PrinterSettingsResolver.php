<?php

declare(strict_types=1);

namespace App\Modules\Shared\Services\Print;

use App\Models\Tenant;

final class PrinterSettingsResolver
{
    private array $settings;

    public function __construct(private readonly Tenant $tenant)
    {
        $this->settings = $this->tenant->settings['pos_hardware'] ?? [];
    }

    public function driver(): string
    {
        return $this->settings['printer_driver'] ?? 'window_print';
    }

    public function host(): string
    {
        return $this->settings['printer_host'] ?? '127.0.0.1';
    }

    public function port(): int
    {
        return (int) ($this->settings['printer_port'] ?? 9100);
    }

    public function cashDrawerEnabled(): bool
    {
        return (bool) ($this->settings['cash_drawer_after_payment'] ?? true);
    }

    public function cashDrawerChannel(): int
    {
        return (int) ($this->settings['cash_drawer_channel'] ?? 2);
    }

    public function usesEscPos(): bool
    {
        return $this->driver() === 'esc_pos';
    }
}
