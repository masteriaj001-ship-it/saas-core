{{-- CajaPage Dashboard --}}

<div class="min-h-screen bg-gray-50">
    
    <!-- Turno Activo o Estado -->
    <div class="p-4 md:p-6 border-b border-gray-200 bg-white">
        <h2 class="text-xl font-medium text-gray-900">Caja - Turnos</h2>
        
        @if($livewire->shifts->count() > 0)
            <!-- Mostrar turno abierto -->
            <div class="p-4 rounded-lg bg-green-50 border border-green-200 mb-4">
                <h3 class="font-semibold text-green-800">Turno Abierto</h3>
                <p class="text-sm text-green-700">
                    Abierto hace <span id="time-open">{{ $livewire->shifts->first()->opened_at ? diffForHumans($livewire->shifts->first()->opened_at) : '---' }}</span>
                    por <span>{{ $livewire->shifts->first()->openedBy->name ?? '---' }}</span>
                </p>
                <p class="text-sm text-green-700 mt-1">
                    Monto inicial: <strong>${{ number_format($livewire->shifts->first()->initial_amount, 2, '.', ',') }}</strong>
                </p>
                <p class="text-sm text-green-700 mt-1">
                    Ventas totales: <strong>${{ number_format($livewire->shifts->first()->totalSales(), 2, '.', ',') }}</strong>
                </p>
                <p class="text-sm text-green-700 mt-1">
                    Gastos totales: <strong>${{ number_format($livewire->shifts->first()->totalExpenses(), 2, '.', ',') }}</strong>
                </p>
                <p class="text-sm text-green-700 mt-1">
                    Efectivo neto: <strong>${{ number_format($livewire->shifts->first()->netAmount(), 2, '.', ',') }}</strong>
                </p>
            </div>
        @else
            <!-- No hay turno abierto - formulario para abrir -->
            <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 mb-4">
                <h3 class="font-semibold text-blue-800">No hay turno activo</h3>
                <p class="text-sm text-blue-700 mb-4">Ingresa el monto inicial para comenzar el turno</p>
                
                <form wire:submit.prevent="openShift">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Monto inicial</label>
                            <input type="number" wire:model.debounce="initial_amount" min="1000" class="w-full rounded border p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            Abrir Turno
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
    
    <!-- Controles de Cierre y Movimientos -->
    <div class="p-4 md:p-6 bg-white rounded-lg">
        
        <!-- Formulario Cerrar Turno -->
        @if($livewire->shifts->count() > 0 && $livewire->shifts->first()->status === 'open')
            <div class="p-4 rounded-lg bg-yellow-50 border border-yellow-200 mb-4">
                <h4 class="font-medium text-yellow-800">Cerrar Turno</h4>
                <p class="text-sm text-yellow-700 mb-2">Ingresa el efectivo contado físicamente:</p>
                
                <form wire:submit.prevent="closeShift">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Efectivo contado</label>
                            <input type="number" wire:model.debounce="actual_cash" min="1000" class="w-full rounded border p-2 focus:outline-none focus:ring-2 focus:ring-yellow-500" required>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Diferencia</label>
                            <input type="text" wire:model="difference" class="w-full rounded border p-2 focus:outline-none focus:ring-2 focus:ring-yellow-500" readonly>
                            <p class="text-xs text-yellow-500 mt-1">Se calcula automáticamente: Contado - Esperado</p>
                        </div>
                    </div>
                    <input type="hidden" wire:model="notes">
                    
                    <div class="flex justify-end mt-4">
                        <button type="button" wire:click="cancelClose" class="btn btn-link text-yellow-700 mr-2">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Confirmar Cierre</button>
                    </div>
                </form>
            </div>
        @endif
        
        <!-- Historial de Movimientos -->
        <div class="overflow-x-auto mt-6">
            <table class="min-w-full bg-white rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora</th>
                        <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                        <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Método</th>
                        <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($livewire->movements as $movement)
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-sm text-gray-500">{{ $movement->created_at->format('H:i') }}</td>
                        <td class="p-3 text-sm font-medium text-gray-900">{{ ucfirst($movement->type) }}</td>
                        <td class="p-3 text-sm text-gray-700">{{ $movement->description }}</td>
                        <td class="p-3 text-sm text-gray-500">{{ $movement->payment_method }}</td>
                        <td class="p-3 text-sm font-medium text-gray-900">${{ number_format($movement->amount, 2, '.', ',') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <!-- Filtros -->
        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
            <div class="grid grid-cols-3 gap-2">
                <select wire:model="filter_type" class="form-select rounded border p-2">
                    <option value="">Todos los tipos</option>
                    <option value="sale">Ventas</option>
                    <option value="expense">Gastos</option>
                    <option value="income">Entradas</option>
                    <option value="refund">Reembolsos</option>
                </select>
                <select wire:model="filter_method" class="form-select rounded border p-2">
                    <option value="">Todos los métodos</option>
                    <option value="cash">Efectivo</option>
                    <option value="card">Tarjeta</option>
                    <option value="transfer">Transferencia</option>
                </select>
                <button wire:click="exportCsv" class="btn btn-sm btn-outline">Exportar CSV</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Calcular diferencia en tiempo real
    livewire.on('update:actual_cash', function (value) {
        // Calcular difference = actual - expected
        const expected = {{ $livewire->shifts->first()?->expected_cash ?? 0 }};
        livewire.set('difference', value - expected);
    });
</script>