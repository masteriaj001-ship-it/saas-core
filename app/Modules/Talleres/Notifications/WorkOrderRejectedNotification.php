<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Notifications;

use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class WorkOrderRejectedNotification extends Notification
{
    public function __construct(
        public readonly string $workOrderCode,
        public readonly string $workOrderTitle,
        public readonly string $url,
        public readonly ?string $rejectionReason = null,
    ) {}

    public function via(Model $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(Model $notifiable): array
    {
        $body = "{$this->workOrderCode} — {$this->workOrderTitle}";

        if ($this->rejectionReason !== null) {
            $body .= "\n\"{$this->rejectionReason}\"";
        }

        return [
            'title' => __('Presupuesto Rechazado'),
            'body' => $body,
            'icon' => Heroicon::XCircle->value,
            'iconColor' => 'danger',
            'format' => 'filament',
            'duration' => 'persistent',
            'actions' => [
                [
                    'name' => 'view',
                    'label' => __('Ver orden'),
                    'url' => $this->url,
                    'color' => 'primary',
                    'size' => 'sm',
                ],
            ],
        ];
    }
}
