<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderStatusEnum: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Received = 'received';
    case Diagnosing = 'diagnosing';
    case Quoted = 'quoted';
    case WaitingApproval = 'waiting_approval';
    case WaitingParts = 'waiting_parts';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Qc = 'qc';
    case WorkDone = 'work_done';
    case EvidencePending = 'evidence_pending';
    case WaitingClient = 'waiting_client';
    case Completed = 'completed';
    case NoPickup = 'no_pickup';
    case Breach = 'breach';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Received => 'Recibido',
            self::Diagnosing => 'En diagnóstico',
            self::Quoted => 'Cotizado',
            self::WaitingApproval => 'Esperando Aprobación',
            self::WaitingParts => 'Esperando Repuestos',
            self::InProgress => 'En reparación',
            self::Paused => 'Pausada',
            self::Qc => 'Control de Calidad',
            self::WorkDone => 'Trabajo terminado',
            self::EvidencePending => 'Evidencia pendiente',
            self::WaitingClient => 'Esperando cliente',
            self::Completed => 'Completado',
            self::NoPickup => 'No recoge',
            self::Breach => 'Incumplimiento',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Received => 'info',
            self::Diagnosing => 'warning',
            self::Quoted => 'primary',
            self::WaitingApproval => 'info',
            self::WaitingParts => 'warning',
            self::InProgress => 'warning',
            self::Paused => 'gray',
            self::Qc => 'purple',
            self::WorkDone => 'success',
            self::EvidencePending => 'warning',
            self::WaitingClient => 'info',
            self::Completed => 'success',
            self::NoPickup => 'danger',
            self::Breach => 'danger',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }
}
