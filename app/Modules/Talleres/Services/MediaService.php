<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Services;

use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderInspection;
use App\Modules\Talleres\Models\WorkOrderMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function upload(
        UploadedFile $file,
        WorkOrder $workOrder,
        ?WorkOrderInspection $inspection = null,
        ?User $user = null,
        array $metadata = [],
    ): WorkOrderMedia {

        $sanitizedName = $this->sanitizeName($file->getClientOriginalName());
        $uuid = (string) Str::uuid();
        $storagePath = sprintf(
            '%s/%s/%s-%s',
            $workOrder->tenant_id,
            $workOrder->id,
            $uuid,
            $sanitizedName,
        );

        Storage::disk('minio')->put(
            $storagePath,
            $file->get(),
            ['visibility' => 'private'],
        );

        return WorkOrderMedia::create([
            'work_order_id' => $workOrder->id,
            'work_order_inspection_id' => $inspection?->id,
            'user_id' => $user?->id,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $storagePath,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'metadata' => array_merge([
                'category' => null,
                'source' => null,
                'uploaded_via' => null,
            ], $metadata),
        ]);
    }

    public function delete(WorkOrderMedia $media): bool
    {
        Storage::disk('minio')->delete($media->storage_path);

        return (bool) $media->delete();
    }

    public function temporaryUrl(WorkOrderMedia $media, ?\DateTimeInterface $expires = null): string
    {
        $expires ??= now()->addHour();

        $url = Storage::disk('minio')->temporaryUrl(
            $media->storage_path,
            $expires,
        );

        if (App::environment('local')) {
            $url = str_replace(
                'http://minio:9000',
                'http://localhost:9000',
                $url,
            );
        }

        return $url;
    }

    public function sanitizeName(string $name): string
    {
        $name = Str::limit($name, 200, '');

        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

        return $name;
    }
}
