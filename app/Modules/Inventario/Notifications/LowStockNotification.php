<?php

declare(strict_types=1);

namespace App\Modules\Inventario\Notifications;

use App\Models\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Item $item,
    ) {}

    public function via(object $notifiable): array
    {
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
}
