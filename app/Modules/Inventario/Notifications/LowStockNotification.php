<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Notifications;

use App\Models\Item;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly ?string $tenantId;

    public function __construct(
        private Item $item,
    ) {
        $this->tenantId = $item->tenant_id;
    }

    public function via(object $notifiable): array
    {
        $this->ensureTenantContext($notifiable);

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Alerta de Stock Bajo: {$this->item->name}")
            ->line("El item **{$this->item->name}** ha alcanzado un nivel crítico de stock.")
            ->line("Stock actual: **{$this->item->stock}**")
            ->line("Stock mínimo: **{$this->item->min_stock}**")
            ->action('Ver Item', url("/admin/inventario/items/{$this->item->id}"))
            ->line('Por favor, realice un pedido de reabastecimiento lo antes posible.');
    }

    private function ensureTenantContext(object $notifiable): void
    {
        $tenantId = $this->tenantId ?? ($notifiable->tenant_id ?? null);

        if (is_string($tenantId) && $tenantId !== '') {
            app(TenantManager::class)->setTenantContext($tenantId);
        }
    }
}
