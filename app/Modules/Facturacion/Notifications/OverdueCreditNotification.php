<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Notifications;

use App\Modules\Facturacion\Models\CreditAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueCreditNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private CreditAccount $account,
        private float $overdueAmount,
        private int $daysOverdue,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Cartera vencida: {$this->account->contact->name}")
            ->line("El cliente **{$this->account->contact->name}** tiene un saldo vencido.")
            ->line('Monto vencido: **$'.number_format($this->overdueAmount, 2).'**')
            ->line("Días de atraso: **{$this->daysOverdue}** días")
            ->line('Balance actual: **$'.number_format((float) $this->account->current_balance, 2).'**')
            ->action('Ver Cuenta', url("/admin/facturacion/credit-accounts/{$this->account->id}"))
            ->line('Por favor, gestione la cobranza lo antes posible.');
    }
}
