<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Notifications;

use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class WorkOrderApprovedNotification extends Notification
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
            'title' => __('Presupuesto Aprobado'),
            'body' => "{$this->workOrderCode} — {$this->workOrderTitle}",
            'icon' => Heroicon::CheckCircle->value,
            'iconColor' => 'success',
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
