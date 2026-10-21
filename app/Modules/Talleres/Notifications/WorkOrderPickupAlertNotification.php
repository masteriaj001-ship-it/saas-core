<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Notifications;

use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class WorkOrderPickupAlertNotification extends Notification
{
    public function __construct(
        public readonly string $workOrderCode,
        public readonly string $workOrderTitle,
        public readonly string $url,
    ) {}

    public function via(Model $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(Model $notifiable): array
    {
        return [
            'title' => __('Entrega pendiente'),
            'body' => "{$this->workOrderCode} — {$this->workOrderTitle}",
            'icon' => Heroicon::Clock->value,
            'iconColor' => 'warning',
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
