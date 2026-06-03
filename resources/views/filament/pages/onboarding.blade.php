<x-filament-panels::page>
    <div class="max-w-3xl mx-auto mt-4">
        <form wire:submit.prevent="complete">
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
