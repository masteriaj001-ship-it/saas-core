<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\RelationManagers;

use App\Modules\Talleres\Models\WorkOrderMedia;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'generalMedia';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                FileUpload::make('upload')
                    ->label('Archivo')
                    ->disk('minio')
                    ->directory('tmp')
                    ->storeFileNamesIn('original_name')
                    ->acceptedFileTypes(['image/*', 'application/pdf', 'video/*'])
                    ->maxSize(102400)
                    ->visibility('private')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('original_name')
                    ->label('Nombre')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('mime_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'image/') => 'success',
                        str_starts_with($state, 'video/') => 'warning',
                        $state === 'application/pdf' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match (true) {
                        str_starts_with($state, 'image/') => 'Imagen',
                        str_starts_with($state, 'video/') => 'Video',
                        $state === 'application/pdf' => 'PDF',
                        default => 'Otro',
                    }),
                TextColumn::make('size')
                    ->label('Tamaño')
                    ->formatStateUsing(fn (int $state): string => $state > 1048576
                        ? round($state / 1048576, 1).' MB'
                        : round($state / 1024, 1).' KB'
                    ),
                TextColumn::make('user.name')
                    ->label('Subido por')
                    ->placeholder('Sistema'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $disk = Storage::disk('minio');
                        $tmpPath = $data['upload'];
                        $originalName = $data['original_name'] ?? basename($tmpPath);
                        $mimeType = $disk->mimeType($tmpPath);
                        $size = $disk->size($tmpPath);
                        $uuid = (string) Str::uuid();
                        $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                        $sanitizedName = Str::limit($sanitizedName, 200, '');
                        $finalPath = sprintf(
                            '%s/%s/%s-%s',
                            $this->getOwnerRecord()->tenant_id,
                            $this->getOwnerRecord()->id,
                            $uuid,
                            $sanitizedName,
                        );
                        $disk->move($tmpPath, $finalPath);

                        return [
                            'storage_path' => $finalPath,
                            'original_name' => $originalName,
                            'mime_type' => $mimeType,
                            'size' => $size,
                            'user_id' => auth()->id(),
                            'metadata' => [
                                'category' => null,
                                'source' => null,
                                'uploaded_via' => 'filament',
                            ],
                        ];
                    }),
            ])
            ->actions([
                DeleteAction::make()
                    ->before(function (WorkOrderMedia $record): void {
                        Storage::disk('minio')->delete($record->storage_path);
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
