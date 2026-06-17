<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\RelationManagers;

use App\Enums\WorkOrderChecklistStatusEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistItems';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('task')
                    ->label('Tarea')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->label('Estado')
                    ->options(WorkOrderChecklistStatusEnum::class)
                    ->required()
                    ->live(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->visible(fn ($get): bool => $get('status') === WorkOrderChecklistStatusEnum::Nok->value),
                Select::make('assigned_to')
                    ->label('Asignado a')
                    ->relationship('assignee', 'name')
                    ->nullable(),
                Hidden::make('position')
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('task')
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('task')
                    ->label('Tarea')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (WorkOrderChecklistStatusEnum $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn (WorkOrderChecklistStatusEnum $state): string => $state->getLabel()),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(50)
                    ->placeholder('—'),
                TextColumn::make('assignee.name')
                    ->label('Asignado')
                    ->placeholder('—'),
                TextColumn::make('completed_at')
                    ->label('Completado')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('position')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
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
