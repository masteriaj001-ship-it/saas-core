# FEATURE_SPEC — Sprint 4: work_order_media

> Estado: Completado | Fecha: 2026-06-04

---

## 1. Entrada (Input)

Archivos subidos por el usuario desde Filament (MediaRelationManager en WorkOrderResource).

### Formularios / Componentes Filament

| Componente | Campo | Tipo | Validación |
|---|---|---|---|
| `FileUpload::make('file')` | `file` | `UploadedFile` | image/*, application/pdf, video/*, max 100MB |

### Datos de Contexto

- `tenant_id`: resuelto por `TenantManager` automáticamente
- `work_order_id`: OT asociada (obligatorio)
- `work_order_inspection_id`: ítem de inspección asociado (nullable)
- `user_id`: del request autenticado

### Metadata Mínima

```json
{
  "category": null,
  "source": null,
  "uploaded_via": null
}
```

---

## 2. Proceso (Processing)

Toda operación de upload/delete/temporaryUrl pasa por `MediaService`.

### MediaService

| Método | Descripción |
|---|---|
| `upload(UploadedFile, WorkOrder, ?Inspection, ?User, array metadata): WorkOrderMedia` | Sanitiza nombre, genera storage_path UUID-based, sube a MinIO, persiste en BD |
| `delete(WorkOrderMedia): bool` | Elimina archivo de MinIO + registro en BD |
| `temporaryUrl(WorkOrderMedia, ?DateTimeInterface): string` | Genera URL temporal firmada |

### Storage Path

`{tenant_id}/{work_order_id}/{uuid}-{sanitized_name}`

- UUID es la identidad real del archivo
- `original_name` sanitizado (solo `[a-zA-Z0-9._-]`, max 200 chars)
- No se confía en el nombre enviado por el cliente

---

## 3. Estado (State)

### Tablas

| Tabla | Operación | RLS |
|---|---|---|
| `work_order_media` | CREATE | ✅ 4 políticas + FORCE |

### Campos

| Campo | Tipo | Requerido | Default | Descripción |
|---|---|---|---|---|
| `id` | `uuid PK` | sí | `gen_random_uuid()` | |
| `tenant_id` | `uuid FK → tenants` | sí | — | RLS |
| `work_order_id` | `uuid FK → work_orders ON DELETE CASCADE` | sí | — | OT asociada |
| `work_order_inspection_id` | `uuid FK → inspections ON DELETE SET NULL` | no | `null` | ítem de inspección |
| `user_id` | `uuid FK → users ON DELETE SET NULL` | no | `null` | quien subió |
| `original_name` | `varchar(255)` | sí | — | solo informativo |
| `storage_path` | `varchar(500)` | sí | — | única referencia técnica |
| `mime_type` | `varchar(127)` | sí | — | `image/jpeg`, `application/pdf`, etc. |
| `size` | `bigint` | sí | — | bytes |
| `metadata` | `jsonb` | sí | `{}` | category, source, uploaded_via |
| `created_at` | `timestamptz` | sí | `now()` | |
| `updated_at` | `timestamptz` | sí | `now()` | |

Sin SoftDeletes.

### Índices

- `(tenant_id)` — `idx_wom_tenant`
- `(tenant_id, work_order_id)` — `idx_wom_work_order`
- `(tenant_id, work_order_inspection_id)` partial — `idx_wom_inspection`

---

## 4. Renderizado (Rendering)

### MediaRelationManager (tab en WorkOrder edit)

- FileUpload con drag & drop
- `->disk('minio')` → almacenamiento S3-compatible
- `->visibility('private')` → archivos no accesibles públicamente
- `->acceptedFileTypes(['image/*', 'application/pdf', 'video/*'])`
- `->maxSize(102400)` (100MB)

Tabla: original_name (searchable), mime_type (badge Imagen/Video/PDF/Otro con colores), size (formateado KB/MB), user.name, created_at (since)

### InspectionsRelationManager (modificado)

- Columna `media_count` con badge (cantidad de fotos asociadas)
- ViewAction con detalle del ítem + conteo de fotos

---

## 5. Salida (Output)

- Archivos almacenados en MinIO bucket `proyect-dashboard`
- URLs temporales firmadas para acceso privado
- Sin InspectionMediaRelationManager independiente (evitar complejidad prematura)

---

## 6. Seguridad

- RLS en tabla nueva: sí (4 políticas estándar + FORCE)
- Sin SoftDeletes
- `photo_path` en `work_order_inspections` permanece como deprecated (no se elimina)
- Visibilidad `private` — sin acceso público directo
- URLs temporales con expiración

---

## 7. Tests (12)

- [x] `test_media_can_be_created` — persistencia BD
- [x] `test_media_tenant_isolation` — RLS cross-tenant
- [x] `test_work_order_has_media_relation` — `$wo->media` retorna colección
- [x] `test_inspection_has_media_relation` — `$inspection->media` retorna colección
- [x] `test_media_can_store_image` — image/jpeg
- [x] `test_media_can_store_pdf` — application/pdf
- [x] `test_media_can_store_video` — video/mp4 (solo persistencia)
- [x] `test_media_deleted_when_work_order_deleted` — CASCADE en forceDelete
- [x] `test_media_preserved_when_inspection_deleted` — SET NULL en inspection_id
- [x] `test_media_private_visibility` — disco configurado
- [x] `test_media_storage_path_uses_uuid` — formato `{tenant}/{wo}/{uuid}-{name}`
- [x] `test_media_disk_configuration_exists` — disco minio con driver s3

---

## 8. Dependencias

- Features previos: Sprint 1, Sprint 2, Sprint 3a, Sprint 3b
- Paquetes nuevos: ninguno (`league/flysystem-aws-s3-v3` ya en lock)
- Servicios externos: MinIO (Docker Compose)

---

## 9. Decisiones Arquitectónicas

| Decisión | Valor |
|---|---|
| Almacenamiento | MinIO (S3-compatible) |
| Driver | Flysystem S3 (`league/flysystem-aws-s3-v3`) |
| Upload | FileUpload Filament + MediaService |
| Naming | `{tenant}/{wo}/{uuid}-{sanitized_name}` |
| Visibilidad | Private (URLs temporales firmadas) |
| Modelo | `WorkOrderMedia` sin SoftDeletes |
| Centralización | No hay `photo_path` en entidades (solo deprecado) |
| Polimórfico global | No implementado — documentado para roadmap futuro |
| Relación inspecciones | `media_count` en InspectionsRelationManager (sin manager separado) |
