<x-filament-panels::page>
    <div class="grid grid-cols-7 gap-1 mb-4">
        @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $day)
            <div class="text-center text-sm font-medium text-gray-500">{{ $day }}</div>
        @endforeach
    </div>

    <div class="grid grid-cols-7 gap-1">
        @for($i = 1; $i <= now()->daysInMonth; $i++)
            @php
                $date = now()->startOfMonth()->addDays($i - 1)->toDateString();
                $appointments = \App\Modules\Talleres\Models\Appointment::where('tenant_id', tenant('id'))
                    ->whereDate('scheduled_at', $date)
                    ->count();
            @endphp
            <div
                class="p-2 border rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 {{ $selectedDate === $date ? 'bg-primary-500 text-white' : '' }}"
                wire:click="setSelectedDate('{{ $date }}')"
            >
                <div class="text-sm font-medium">{{ $i }}</div>
                @if($appointments > 0)
                    <div class="text-xs">{{ $appointments }} cita(s)</div>
                @endif
            </div>
        @endfor
    </div>

    <div class="mt-6">
        <h3 class="text-lg font-medium mb-4">Citas del {{ $selectedDate }}</h3>
        <div class="space-y-2">
            @forelse($this->getAppointments() as $appointment)
                <div class="p-4 border rounded">
                    <div class="flex justify-between">
                        <div>
                            <div class="font-medium">{{ $appointment->title }}</div>
                            <div class="text-sm text-gray-500">{{ $appointment->contact->name }}</div>
                            <div class="text-sm text-gray-500">{{ $appointment->vehicle->display_name ?? 'Sin vehículo' }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm">{{ $appointment->scheduled_at->format('H:i') }}</div>
                            <div class="text-sm text-gray-500">{{ $appointment->duration_minutes }} min</div>
                            <div class="text-sm">{{ $appointment->bay->name ?? 'Sin bahía' }}</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-gray-500 py-8">No hay citas para esta fecha</div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
