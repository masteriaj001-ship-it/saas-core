<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages\CreateAppointment;
use App\Filament\Resources\AppointmentResource\Pages\EditAppointment;
use App\Filament\Resources\AppointmentResource\Pages\ListAppointments;
use App\Modules\Talleres\Enums\AppointmentStatus;
use App\Modules\Talleres\Models\Appointment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 21;

    public static function getNavigationLabel(): string
    {
        return 'Citas';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Talleres';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Información General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Título'))
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->label(__('Estado'))
                            ->required()
                            ->default(AppointmentStatus::SCHEDULED)
                            ->options(AppointmentStatus::class),
                        Select::make('contact_id')
                            ->label(__('Cliente'))
                            ->relationship('contact', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('client_vehicle_id')
                            ->label(__('Vehículo'))
                            ->relationship('vehicle', 'display_name')
                            ->searchable()
                            ->preload(),
                        Select::make('bay_id')
                            ->label(__('Bahía'))
                            ->relationship('bay', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('mechanic_id')
                            ->label(__('Mecánico'))
                            ->relationship('mechanic', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('location_id')
                            ->label(__('Ubicación'))
                            ->relationship('location', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make(__('Programación'))
                    ->columns(2)
                    ->schema([
                        DatePicker::make('scheduled_at')
                            ->label(__('Fecha'))
                            ->required(),
                        TimePicker::make('scheduled_at_time')
                            ->label(__('Hora'))
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label(__('Duración (minutos)'))
                            ->required()
                            ->numeric()
                            ->default(60),
                    ]),
                Section::make(__('Descripción'))
                    ->schema([
                        Textarea::make('description')
                            ->label(__('Descripción'))
                            ->rows(3),
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(3),
                    ]),
                Section::make(__('Metadatos'))
                    ->schema([
                        KeyValue::make('metadata')
                            ->label(__('Metadatos'))
                            ->keyLabel(__('Clave'))
                            ->valueLabel(__('Valor')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact.name')
                    ->label(__('Cliente'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle.display_name')
                    ->label(__('Vehículo'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bay.name')
                    ->label(__('Bahía'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mechanic.name')
                    ->label(__('Mecánico'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Estado'))
                    ->badge()
                    ->color(fn (AppointmentStatus $state): string => match ($state) {
                        AppointmentStatus::SCHEDULED => 'gray',
                        AppointmentStatus::CONFIRMED => 'info',
                        AppointmentStatus::IN_PROGRESS => 'warning',
                        AppointmentStatus::COMPLETED => 'success',
                        AppointmentStatus::CANCELLED => 'danger',
                    }),
                TextColumn::make('scheduled_at')
                    ->label(__('Fecha'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label(__('Duración'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Estado'))
                    ->options(AppointmentStatus::class),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
            'create' => CreateAppointment::route('/create'),
            'edit' => EditAppointment::route('/{record}/edit'),
        ];
    }
}
