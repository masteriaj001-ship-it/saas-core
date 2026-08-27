<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use App\Modules\Talleres\Models\Appointment;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class AppointmentCalendar extends Page
{
    protected static string $resource = AppointmentResource::class;

    protected string $view = 'filament.resources.appointment-resource.pages.appointment-calendar';

    public ?string $selectedDate = null;

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
    }

    public function getAppointments(): Collection
    {
        return Appointment::where('tenant_id', tenant('id'))
            ->whereDate('scheduled_at', $this->selectedDate)
            ->with(['contact', 'vehicle', 'bay', 'mechanic'])
            ->orderBy('scheduled_at')
            ->get();
    }

    public function setSelectedDate(string $date): void
    {
        $this->selectedDate = $date;
    }
}
