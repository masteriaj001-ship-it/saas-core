<x-filament-panels::page>
    <div class="max-w-4xl mx-auto mt-4">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-100 tracking-tight">
                ⚡ {{ __('Configura tu Taller Mecánico') }}
            </h1>
            <p class="mt-2 text-sm text-gray-400">
                {{ __('Crea tu cuenta y configura tu taller en tres pasos.') }}
            </p>
        </div>

        <form wire:submit.prevent="complete" class="space-y-6">
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
