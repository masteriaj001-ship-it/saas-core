<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Enums\InspectionItemStatusEnum;
use App\Filament\Resources\WorkOrderResource;
use App\Modules\Talleres\Services\WorkOrderCodeGenerator;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

class CreateWorkOrder extends CreateRecord
{
    use HasWizard;

    protected static string $resource = WorkOrderResource::class;

    protected function getSteps(): array
    {
        return [
            Step::make(__('Recepción'))
                ->icon(Heroicon::ClipboardDocumentList)
                ->description(__('Cliente, vehículo y datos de ingreso'))
                ->columns(3)
                ->schema(WorkOrderResource::step1Schema()),
            Step::make(__('Diagnóstico'))
                ->icon(Heroicon::MagnifyingGlass)
                ->description(__('Problema, diagnóstico e insumos'))
                ->columns(3)
                ->schema(WorkOrderResource::step2Schema()),
            Step::make(__('Cierre'))
                ->icon(Heroicon::CheckBadge)
                ->description(__('Control de calidad, inspección y fechas'))
                ->columns(3)
                ->schema(WorkOrderResource::step3Schema()),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = app(WorkOrderCodeGenerator::class)->next();

        return $data;
    }

    protected function afterCreate(): void
    {
        $defaults = config('inspection-defaults.mechanic', []);

        foreach ($defaults as $index => $itemName) {
            $this->record->inspections()->create([
                'item_name' => $itemName,
                'status' => InspectionItemStatusEnum::Ok,
                'sort_order' => $index,
            ]);
        }
    }
}
