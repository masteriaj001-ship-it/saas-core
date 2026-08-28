<x-filament-panels::page>
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Caja / Turno') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if($currentShift)
                    {{ __('Turno abierto hace') }} {{ $cards['tiempo_abierto'] ?? '' }} {{ __('por') }} {{ $cards['abierto_por'] ?? '' }}
                @else
                    {{ __('No hay turno activo') }}
                @endif
            </p>
        </div>
        @if($currentShift)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
                {{ __('Abierto') }}
            </span>
        @endif
    </div>

    {{-- No shift open: form to open --}}
    @if(! $currentShift)
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-950/30">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/50">
                    <x-heroicon-o-currency-dollar class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">{{ __('Abrir Nuevo Turno') }}</h3>
                    <p class="text-sm text-blue-700 dark:text-blue-300">{{ __('Ingresa el efectivo inicial en caja') }}</p>
                </div>
            </div>

            <form wire:submit="openShift" class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        {{ __('Abrir Turno') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Shift open: dashboard --}}
    @if($currentShift && $cards)
        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Ventas Totales --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                        <x-heroicon-o-arrow-trending-up class="h-5 w-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Ventas Totales') }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">${{ $cards['ventas_totales'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Gastos --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                        <x-heroicon-o-arrow-trending-down class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Gastos') }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">${{ $cards['gastos'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Neto --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                        <x-heroicon-o-calculator class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Neto') }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">${{ $cards['neto'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Monto Inicial --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                        <x-heroicon-o-banknotes class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Monto Inicial') }}</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">${{ $cards['monto_inicial'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment Methods Breakdown --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Efectivo') }}</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white">${{ $cards['efectivo'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Tarjeta') }}</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white">${{ $cards['tarjeta'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Transferencia') }}</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white">${{ $cards['transferencia'] }}</p>
            </div>
        </div>

        {{-- Actions Row --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Record Expense --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ __('Registrar Gasto') }}</h3>
                <form wire:submit="recordExpense" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Descripción') }}</label>
                        <input type="text" wire:model="expenseDescription" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="{{ __('Ej: Compra de insumos') }}" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Monto') }}</label>
                        <input type="number" wire:model="expenseAmount" min="0" step="100" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="0" />
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        {{ __('Registrar Gasto') }}
                    </button>
                </form>
            </div>

            {{-- Close Shift --}}
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-6 shadow-sm dark:border-yellow-800 dark:bg-yellow-950/30">
                <h3 class="mb-4 text-lg font-semibold text-yellow-900 dark:text-yellow-100">{{ __('Cerrar Turno') }}</h3>
                <form wire:submit="closeShift" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-yellow-800 dark:text-yellow-200">{{ __('Efectivo Contado Físicamente') }}</label>
                        <input type="number" wire:model.live="actualCash" min="0" step="100" class="w-full rounded-lg border border-yellow-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-yellow-500 focus:outline-none focus:ring-1 focus:ring-yellow-500 dark:border-yellow-700 dark:bg-gray-700 dark:text-white" placeholder="0" />
                    </div>

                    @if($difference !== null)
                        <div class="rounded-lg p-3 {{ $difference >= 0 ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30' }}">
                            <p class="text-sm font-medium {{ $difference >= 0 ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                                {{ $difference >= 0 ? __('Sobrante') : __('Faltante') }}: ${{ number_format(abs($difference), 2, ',', '.') }}
                            </p>
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-sm font-medium text-yellow-800 dark:text-yellow-200">{{ __('Notas') }}</label>
                        <textarea wire:model="closeNotes" rows="2" class="w-full rounded-lg border border-yellow-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-yellow-500 focus:outline-none focus:ring-1 focus:ring-yellow-500 dark:border-yellow-700 dark:bg-gray-700 dark:text-white" placeholder="{{ __('Observaciones del cierre...') }}"></textarea>
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-yellow-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                        {{ __('Confirmar Cierre') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Movements Table --}}
        @if(count($movements) > 0)
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Movimientos del Turno') }} ({{ count($movements) }})</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3">{{ __('Hora') }}</th>
                                <th class="px-6 py-3">{{ __('Tipo') }}</th>
                                <th class="px-6 py-3">{{ __('Descripción') }}</th>
                                <th class="px-6 py-3">{{ __('Método') }}</th>
                                <th class="px-6 py-3 text-right">{{ __('Monto') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($movements as $movement)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="whitespace-nowrap px-6 py-3 text-gray-500 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($movement['created_at'])->format('H:i') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3">
                                        @php
                                            $typeColors = [
                                                'sale' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                                'expense' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                                'income' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                                'refund' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                            ];
                                            $typeLabels = [
                                                'sale' => 'Venta',
                                                'expense' => 'Gasto',
                                                'income' => 'Ingreso',
                                                'refund' => 'Reembolso',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typeColors[$movement['type']] ?? '' }}">
                                            {{ $typeLabels[$movement['type']] ?? ucfirst($movement['type']) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-900 dark:text-white">{{ $movement['description'] }}</td>
                                    <td class="whitespace-nowrap px-6 py-3 text-gray-500 dark:text-gray-400">
                                        {{ ucfirst($movement['payment_method']) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3 text-right font-medium {{ $movement['amount'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400' }}">
                                        ${{ number_format($movement['amount'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
</x-filament-panels::page>
