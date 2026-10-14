Eres el paso "plan" de un pipeline de desarrollo. Decisión, no implementación.

Entrada: artifacts/spec.md (léelo completo).

Stack del proyecto: Laravel 13 + Filament 5.6 + Livewire 4 + PostgreSQL (Docker/Sail).
Tests: PHPUnit 12 (NO Pest — no uses expect(), it()/test(), beforeEach() estilo Pest).
NO existe PHPStan en este repo. Linter: Pint. E2E: existe Dusk pero se excluye por defecto.

COMANDOS: todo va prefijado con `vendor/bin/sail` — NUNCA `php artisan ...` ni
`vendor/bin/pint` crudos en el host (la base de datos solo resuelve dentro de Sail).

Haz:
1. Diseña el plan descomponiendo la feature en tareas pequeñas y verificables.
2. Para CADA comportamiento nuevo, crea DOS tareas encadenadas:
   - "test: <comportamiento>" — escribe un test PHPUnit QUE FALLE ahora (rojo)
   - "impl: <comportamiento>" — implementa hasta que el test pase (verde)

Escribe DOS artefactos:

artifacts/tasks.json — array:
[{"id": "T1", "title": "test: ...", "type": "test", "files": ["tests/Feature/..."],
  "depends_on": [], "verify": "vendor/bin/sail artisan test --compact --filter=NombreTest"},
 {"id": "T2", "title": "impl: ...", "type": "impl", "files": ["app/..."],
  "depends_on": ["T1"], "verify": "vendor/bin/sail artisan test --compact --filter=NombreTest"}, ...]

artifacts/ac-map.json — array:
[{"criterion": "descripción del criterio",
  "test": "vendor/bin/sail artisan test --compact --filter=NombreDelTest",
  "task": "T2", "livewire_component": "App\\Filament\\Resources\\XResource\\Pages\\ListX (si aplica)"}, ...]

Reglas para los tests PHPUnit 12:
- Estilo del repo: mira tests/ existentes (p. ej. CashShiftTableTest, StockMovementResourceTest)
  y COPIA su patrón: atributos #[Test], setup de autenticación/permisos, RefreshDatabase, factories.
- Patrón real de auth/permisos en tests de recursos (copia el setup de CashShiftTableTest):
  actingAs($this->user) → app(TenantManager::class)->setTenantContext($this->tenant->id)
  → $this->seed(RolePermissionSeeder::class)
  → app(PermissionRegistrar::class)->forgetCachedPermissions()
- Tests de recursos Filament: Livewire::test(Componente::class) + las assertions de Filament
  Testing (assertCanSeeTableRecords, assertTableActionExists, assertFormSet, assertNotified...).
  Filament 5.6 corre sobre Livewire 4 — verifica nombres de métodos/argumentos en los tests existentes
  antes de asumirlos.
- Reglas ->rule(...): verifica la firma real en vendor/filament antes de escribir la task.
  Filament evalúa el closure que tú le pases (devuelve la rule); la closure-rule de Laravel
  es la interna y recibe $fail. Un doble cierre mal modelado revienta en runtime, no en plan.
- Tests de lógica de negocio: PHPUnit puro ($this->assertEquals, assertDatabaseHas...).
- factories: usa las existentes; si falta una, añade tarea "factory: ...".
- "verify" siempre es un comando ejecutable y aislado (--filter) con prefijo vendor/bin/sail.

Reglas del repo (mandatorias — léelas en AGENTS.md y ARCHITECTURE_MANIFEST.md):
- Lógica de negocio solo en app/Modules/{Modulo}/ — NO en app/Services/ ni app/Http/.
- Naming canónico sin redundancia: Asset, Item, Contact, Transaction, Member.
- tenant_id se inyecta vía trait; nunca por input del usuario; $guarded incluye tenant_id.
- Sin paquetes nuevos sin aprobación explícita de John.

- No implementes nada. Solo planifica.
