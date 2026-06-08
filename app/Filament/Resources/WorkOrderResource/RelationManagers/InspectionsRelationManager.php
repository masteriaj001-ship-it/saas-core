<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\RelationManagers;

use App\Enums\InspectionItemStatusEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InspectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'inspections';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('item_name')
                    ->label('Elemento')
                    ->required()
                    ->maxLength(100),
                Select::make('status')
                    ->label('Estado')
                    ->options(InspectionItemStatusEnum::class)
                    ->required()
                    ->live(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->visible(fn ($get): bool => $get('status') !== InspectionItemStatusEnum::Ok->value),
                Hidden::make('sort_order')
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_name')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('item_name')
                    ->label('Elemento')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (InspectionItemStatusEnum $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn (InspectionItemStatusEnum $state): string => $state->getLabel()),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(50)
                    ->placeholder('—'),
                TextColumn::make('media_count')
                    ->label('Fotos')
                    ->counts('media')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading('Detalle de inspección')
                    ->modalDescription(fn ($record): string => $record->item_name)
                    ->modalContent(function ($record): string {
                        $count = $record->media()->count();

                        return '<div class="p-4 text-sm">'
                            ."<p>Estado: <strong>{$record->status->getLabel()}</strong></p>"
                            .($record->notes ? "<p>Notas: {$record->notes}</p>" : '')
                            ."<p>Fotos asociadas: <strong>{$count}</strong></p>"
                            .'</div>';
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
