# FEATURE_SPEC: Facturación API REST

## Resumen

API REST versionada para facturación electrónica (FE) y documentos equivalentes POS.
Soporta dos regímenes fiscales colombianos: **declarante** (factura FE con IVA 19%) y **no-declarante** (documento POS, IVA 0%).

---

## Stack

| Componente | Versión |
|---|---|
| Laravel | ^13 |
| PostgreSQL | 16 |
| Filament | ^5 (solo admin, la API no depende de Filament) |
| Spatie Activitylog | ^5 (auditoría de cambios de régimen) |

---

## Decisiones de diseño

### POS = PDF interno con numeración secuencial

Los documentos POS usan numeración propia (`POS-{000000}`), independiente de la secuencia FE (`FE-{000000}`).
La secuencia se managea vía `document_sequences` con `SELECT ... FOR UPDATE` por tenant y tipo de documento.

### Cambio de régimen manual desde settings

El tenant se configura como `declarante` o `no_declarante` vía `settings.regimen` en la tabla `tenants`.
El cambio es manual, queda auditado en `activity_log`, y los documentos históricos preservan su tipo original.

### Credenciales FE globales (MVP)

En MVP las credenciales de facturación electrónica son globales (`.env`).
El path de migración futuro está documentado en `docs/architecture/BILLING.md`.

---

## Modelos

### Invoice

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | UUID (PK) | |
| `tenant_id` | UUID (FK) | |
| `document_type` | enum | `invoice`, `credit_note`, `pos` |
| `document_number` | string | `FE-{000000}`, `NC-{000000}`, `POS-{000000}` |
| `sequence` | integer|null | Secuencia FE/NC. Null para POS. |
| `pos_sequence` | integer|null | Secuencia POS. Null para FE/NC. |
| `status` | enum | `draft`, `issued`, `cancelled` |
| `contact_id` | UUID (FK, nullable) | |
| `work_order_id` | UUID (FK, nullable) | |
| `subtotal`, `discount_total`, `tax_total`, `grand_total` | decimal | |
| `cufe` | string|null | CUFE para FE (futuro) |
| `qr_code_url` | string|null | URL QR para FE (futuro) |
| `notes` | text | |
| `issued_at`, `due_at` | datetime | |

### DocumentSequence

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | UUID (PK) | |
| `tenant_id` | UUID (FK) | |
| `type` | string | `FE`, `NC`, `POS` |
| `prefix` | string | `FE`, `NC`, `POS` |
| `last_number` | integer | Último número usado |
| UNIQUE(tenant_id, type) | | |

### InvoiceItem

| Campo | Tipo |
|---|---|
| `id` | UUID (PK) |
| `invoice_id` | UUID (FK) |
| `description` | string |
| `quantity` | decimal |
| `unit_price` | decimal |
| `discount` | decimal |
| `tax_rate` | decimal |
| `tax_amount` | decimal |
| `subtotal` | decimal |
| `total` | decimal |

---

## Servicios

### DocumentSequenceService

- `reserve(string $type): array` — Obtiene el próximo número con `lockForUpdate` por tenant y tipo.
- Thread-safe via `SELECT ... FOR UPDATE` en PostgreSQL.

### InvoiceCreationService

- `create(Tenant $tenant, InvoiceDocumentTypeEnum $type, array $data): Invoice`
- Lógica de impuestos según régimen del tenant:
  - **Declarante**: IVA 19% sobre subtotal - descuentos.
  - **No-declarante**: IVA 0%.
- Asignación de secuencia (FE via `DocumentSequenceService`, POS via secuencia propia).
- Si `contact_id` es null, crea contacto nominal con `contact_name`.

---

## API Endpoints

Base path: `/api/v1/invoices`

| Método | Ruta | Descripción | Controlador |
|---|---|---|---|
| GET | `/invoices` | Listar (paginado, filtros) | `index` |
| POST | `/invoices` | Crear (detecta régimen vía tenant) | `store` |
| GET | `/invoices/{invoice}` | Obtener | `show` |
| PUT | `/invoices/{invoice}` | Actualizar (solo draft) | `update` |
| POST | `/invoices/{invoice}/cancel` | Cancelar (solo issued) | `cancel` |

### Filtros (index)
- `status`: `draft`, `issued`, `cancelled`
- `document_type`: `invoice`, `credit_note`, `pos`
- `from`, `to`: rango de fechas (created_at)
- `per_page`: máximo 100, default 15

### Rate limiting
- `throttle:60,1` — 60 requests por minuto.

### Responses
- Todos los endpoints retornan `InvoiceResource` envuelto en `data`.
- `store` retorna 201 (created).
- `cancel` retorna 200 con status `cancelled`.
- Errores de validación retornan 422.

### Determinación de tipo de documento
`Tenant::documentTypeForRegimen()`:
- `declarante` + responsable IVA → `InvoiceDocumentTypeEnum::Invoice`
- `no_declarante` + no responsable IVA → `InvoiceDocumentTypeEnum::Pos`

---

## Tests

| Archivo | Tests | Descripción |
|---|---|---|
| `InvoiceApiTest.php` | 6 | CRUD + cancelación (declarante + POS) |
| `DocumentSequenceTest.php` | 3 | Secuencia FE, POS, concurrencia |
| `RegimenTest.php` | 3 | Determinación de tipo, cambio de régimen, auditoría |
| `InvoiceApiRlsTest.php` | 3 | Aislamiento RLS multi-tenant |

Total: **16 tests**.

---

## Rate limiting

- Middleware `throttle:60,1` en todas las rutas de la API.
- Sin named limiter personalizado en MVP.

---

## Pendientes (deuda técnica)

- `docs/architecture/BILLING.md` — path de migración de credenciales FE globales → por tenant.
- `config/facturacion.php` — archivo de configuración del módulo.
- Migrar `InvoiceCodeGenerator` legacy a `DocumentSequenceService` (código duplicado temporal).
