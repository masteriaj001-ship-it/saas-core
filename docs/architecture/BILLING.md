# Arquitectura de Facturación Electrónica (FE)

## Estado actual (MVP)

Las credenciales de facturación electrónica son **globales para todos los tenants**.
Se configuran vía variables de entorno en `.env`:

```env
FE_API_URL=https://api-fe.dian.gov.co
FE_API_KEY=clave-compartida-mvp
FE_API_SECRET=secreto-compartido-mvp
FE_API_ENVIRONMENT=test
```

Estas credenciales son leídas por `config/facturacion.php` (pendiente de crear) y usadas por todos
los tenants declarantes del sistema.

### ¿Por qué globales en MVP?

- Velocidad de desarrollo: una sola integración FE para todos los tenants.
- Sin proveedor multi-tenant definido aún.
- Sin UI de configuración de credenciales por tenant.

---

## Stack de facturación

| Componente | Propósito |
|---|---|
| `DocumentSequenceService` | Numeración secuencial FE/NC/POS por tenant (lockForUpdate) |
| `InvoiceCreationService` | Creación de invoices con lógica de impuestos según régimen |
| `InvoiceDocumentTypeEnum` | Invoice (FE), CreditNote (NC), Pos |
| `Tenant::settings.regimen` | `declarante` o `no_declarante` |
| `Tenant::settings.es_responsable_iva` | Determina si aplica IVA |

### Regímenes fiscales

| Régimen | Documento | IVA | Secuencia |
|---|---|---|---|
| Declarante + responsable IVA | Invoice (FE) | 19% | `FE-{000000}` |
| No-declarante | POS | 0% | `POS-{000000}` |

### Prefijos de numeración

| Tipo | Prefix | Formato |
|---|---|---|
| Invoice | `FE` | `FE-{000000}` |
| Credit Note | `NC` | `NC-{000000}` |
| POS | `POS` | `POS-{000000}` |

Los prefijos son fijos (no configurables por tenant en MVP).
La unicidad se garantiza vía `UNIQUE(tenant_id, type)` en `document_sequences`.

---

## Path de migración: credenciales globales → por tenant

### Fase 1: Config multi-proveedor (preparación)

1. Extender `tenants.settings` para incluir configuración FE:
   ```json
   {
     "facturacion": {
       "provider": "dian",
       "credentials": "tenant" | "global",
       "api_url": null,
       "api_key": null,
       "api_secret": null
     }
   }
   ```
2. Crear `config/facturacion.php` que lea de `.env` (fallback) o de `tenant.settings`.
3. Agregar un resolver de proveedor en `InvoiceCreationService`:
   ```php
   $creds = $tenant->settings['facturacion']['credentials'] ?? 'global';
   $provider = $creds === 'tenant'
       ? new TenantProvider($tenant)
       : new GlobalProvider();
   ```

### Fase 2: Migración de datos

4. Artisan command `facturacion:migrate-credentials` que copie credenciales globales a los
   tenants que no tienen credenciales propias:
   ```bash
   php artisan facturacion:migrate-credentials --tenant=<uuid> [--all]
   ```
5. El command debe:
   - Setear `settings.facturacion.credentials = 'tenant'`.
   - Copiar los valores de `.env` a `settings.facturacion`.
   - Loggear en `activity_log` el cambio.

### Fase 3: UI de configuración

6. Agregar sección de Facturación Electrónica en Superadmin `TenantResource`:
   - Toggle: "Usar credenciales propias" (solo visible si el tenant es declarante).
   - Campos: API URL, API Key, API Secret (encryptados).
7. Validar credenciales antes de guardar (llamada de prueba al proveedor FE).

---

## Cuándo migrar a credenciales por tenant

- **Cuando el primer tenant solicite su propia cuenta FE** (no la corporativa compartida).
- **Cuando la cantidad de tenants declarantes activos supere 5**, y el proveedor FE
  requiera cuentas individuales por contribuyente.
- **Antes de salir a producción con tenants reales**, si el proveedor FE exige
  credenciales por NIT (lo más probable en DIAN colombiana).
- **Cuando un tenant cambie de régimen** de no-declarante a declarante (necesitará
  su propia habilitación FE).

### Trigger: ¿cómo saber que llegó el momento?

- KPI monitoreable: `Tenant::where('settings->regimen', 'declarante')->count()`
- Alerta automática recomendada cuando `count >= 3` (prepara el path antes de que
  la operación lo exija).
- Event de onboarding: al crear un tenant declarante, verificar si ya se superó
  el umbral de credenciales personalizadas necesarias.

Sin estos criterios, "path de migración futuro" queda como deuda técnica permanente
porque nadie sabe cuándo activarla. Se recomienda establecer el trigger cuando el
primer tenant real (no demo) requiera facturación electrónica.
