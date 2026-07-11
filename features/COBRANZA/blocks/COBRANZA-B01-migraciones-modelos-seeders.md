---
tipo: bloque
proyecto: api
feature: COBRANZA
id: COBRANZA-B01
proyectos: [api]
estado: done
depende_de: [PROPIEDADES-B01, DIRECTORIO-B01]
contrato: null
verificacion_critica: false
actualizado: 2026-07-11
---

# COBRANZA-B01 — Migraciones, modelos y seeders de las 8 tablas de facturación

## Objetivo

Crear las 8 tablas del dominio de facturación (`charge_concepts`, `billing_periods`, `billing_runs`,
`invoices`, `invoice_items`, `payment_receipts`, `payment_allocations`, `peace_certificates`), sus
modelos Eloquent con traits estandarizados, y el seed de los 11 permisos RBAC nuevos del catálogo de
`PANORAMA.md` §5. Este bloque no expone endpoints ni contiene lógica de negocio — solo la estructura
de datos y autorización sobre la que se construye el resto del feature (mismo patrón que
[[../../PROPIEDADES/blocks/PROPIEDADES-B01-migraciones-modelos-seeders]]).

## Alcance

- **Incluye:**
  - 8 migraciones con columnas, FKs, índices únicos y `down()` reversible — esquema exacto de
    `PANORAMA.md` §4, incluyendo:
    - `billing_runs`: `UNIQUE(billing_period_id) WHERE estado = 'completado' AND deleted_at IS NULL`
      como constraint de BD (decisión 7 del panorama, endurece R-COB-09).
    - `charge_concepts.tipo` y `metodo_calculo`: CHECK constraint restringiendo al set cerrado
      (`administracion`/`fondo_imprevistos`/`multa`/`extraordinaria` y
      `coeficiente`/`fijo`/`por_area`/`manual` respectivamente) — mismo criterio de defensa en
      profundidad que `PROPIEDADES-B01` con `property_coefficients.tipo`.
    - `payment_receipts.medio`: CHECK constraint cerrado a `efectivo`/`banco` (R-COB-15). Sin columna
      `transaction_id` (eliminada de Fase 1, decisión 6 revisada).
    - Índices: `invoices(property_id, estado)`, `invoices(condominium_id, billing_period_id)`,
      `invoices.fecha_vencimiento`, `invoice_items.invoice_id`, `payment_allocations.invoice_id`.
    - `invoices.estado` **no es columna almacenada** — se deja fuera de la migración (decisión
      revisada R-COB-08, calculado en el backend al serializar, ver `COBRANZA-B04`).
  - `created_by`/`updated_by` (UUID nullable, FK `→ users.id`) en las 8 tablas, salvo donde
    `PANORAMA.md` §4 exige actor obligatorio: `billing_runs.ejecutado_por` y
    `peace_certificates.emitido_por` son `NOT NULL` (decisión 4).
  - 8 modelos Eloquent con traits: `HasUuidV7`, `SoftDeletes`.
  - Relaciones Eloquent completas según §4: `BillingPeriod → billingRuns`, `BillingRun →
    billingPeriod`, `Invoice → billingPeriod`, `Invoice → billingRun`, `Invoice → property` (lectura
    cross-context, ver [[../../../shared/adr/ADR-002-lectura-cross-context-modulo-monolito]]),
    `Invoice → items` (`invoiceItems`), `InvoiceItem → invoice`, `InvoiceItem → chargeConcept`,
    `PaymentReceipt → property` (cross-context), `PaymentReceipt → contact` (cross-context, lectura de
    `Directorio\Models\Contact`), `PaymentReceipt → allocations`, `PaymentAllocation →
    paymentReceipt`, `PaymentAllocation → invoice`, `PeaceCertificate → property` (cross-context).
    Además `createdBy`/`updatedBy`/`ejecutadoPor`/`emitidoPor` (`belongsTo User`) donde aplique.
  - Seed de los 11 permisos RBAC nuevos del catálogo (`PANORAMA.md` §5): `cobranza.conceptos.ver`,
    `cobranza.conceptos.gestionar`, `cobranza.periodos.ver`, `cobranza.facturacion.ejecutar`,
    `cobranza.facturas.ver`, `cobranza.facturas.gestionar`, `pagos.registrar`, `pagos.anular`,
    `cobranza.paz_salvo.generar`, `cobranza.paz_salvo.revocar`. No se crea `billing.ver` — **ya
    existe** (creado por `DASHBOARD`), se reutiliza sin duplicar.
  - Registro del seeder de permisos en `DatabaseSeeder`.

- **No incluye (explícitamente fuera de este bloque):**
  - Endpoints HTTP, controllers, FormRequests, API Resources, middleware.
  - Lógica de negocio (prorrateo, cálculo de `saldo`, derivación de `estado` de factura, locking de
    pagos, generación de PDF).
  - Asignación de los permisos nuevos a roles de sistema existentes — el seed solo crea el permiso;
    qué rol lo tiene por defecto es una decisión de aplicación que vive en el bloque que primero lo
    consume (`COBRANZA-B02` en adelante), consistente con cómo `AUTH`/`PROPIEDADES` ya manejaron
    permisos nuevos.
  - Tests de feature/integración HTTP — solo tests de migraciones (reversibilidad) y de modelos
    (relaciones, traits, constraints).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | DB con `PROPIEDADES`/`DIRECTORIO` migradas | `php artisan migrate` | 8 tablas nuevas creadas con columnas, FKs e índices según PANORAMA §4 |
| 2 | Tablas creadas | `php artisan migrate:rollback` | 8 tablas eliminadas sin error |
| 3 | Tablas eliminadas | `php artisan migrate` | 8 tablas re-creadas sin error (prueba de `down()` reversible) |
| 4 | Tablas creadas | `php artisan db:seed --class=CobranzaPermissionsSeeder` | 11 permisos nuevos insertados; `billing.ver` **no** duplicado (se verifica que sigue existiendo un solo registro) |
| 5 | Modelos registrados | `$billingPeriod->billingRuns()->create([...])` | Relación `hasMany` funciona |
| 6 | Modelos registrados | `$invoice->items()->create([...])` | Relación `hasMany` hacia `invoice_items` funciona |
| 7 | Modelos registrados | `Invoice::find($id)->property` (cross-context) | Devuelve la instancia de `Properties\Models\Property` correspondiente, solo lectura |
| 8 | Modelos registrados | Crear `billing_runs` con `billing_period_id` inexistente | Error de FK — integridad referencial |
| 9 | Dos filas `billing_runs` para el mismo `billing_period_id` | Ambas con `estado = 'completado'` | La segunda inserción falla por el `UNIQUE ... WHERE estado = 'completado'` — constraint de BD (decisión 7) |
| 10 | Tablas creadas | Insertar `charge_concepts` con `tipo = 'interes'` (fuera del set cerrado) directo a BD | Error de CHECK constraint — la BD rechaza el insert |
| 11 | Tablas creadas | Insertar `payment_receipts` con `medio = 'pse'` directo a BD | Error de CHECK constraint (R-COB-15) |
| 12 | Modelos registrados | `$invoice->delete()` | Soft delete: `deleted_at` se llena, registro no aparece en queries por defecto |
| 13 | Modelos registrados | `$chargeConcept->id` en modelo nuevo | UUID v7 generado automáticamente |
| 14 | Modelos registrados | Crear `billing_runs` sin `ejecutado_por` | Error — columna `NOT NULL` (decisión 4) |
| 15 | Modelos registrados | Crear `peace_certificates` sin `emitido_por` | Error — columna `NOT NULL` (decisión 4) |
| 16 | Seed ejecutado | `Permission::where('name', 'billing.ver')->count()` | `1` — el seeder de COBRANZA no crea un duplicado del permiso ya existente de DASHBOARD |

## Contrato

Este bloque no produce ni consume contrato — es puramente estructural.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] Migraciones con `down()` reversible confirmado: `migrate` → `migrate:rollback` → `migrate` sin
      error — salida pegada.
- [x] Seed de permisos ejecutado — salida pegada confirmando 11 permisos nuevos y cero duplicados de
      `billing.ver`.
- [x] Tests que cubren los criterios 5–16 (relaciones incluida la lectura cross-context de
      `Properties`, constraints, UUID v7, soft delete, actor obligatorio, CHECK de `tipo`/`metodo_calculo`/`medio`)
      — todos pasando, salida completa pegada.
- [x] `api/API_DATABASE.md` actualizado con las 8 tablas nuevas (esquema real documentado).
- [x] `shared/GLOSSARY.md` actualizado con los términos nuevos listados en `PANORAMA.md` §8 — ya
      estaban completos desde la aprobación del panorama, confirmado sin cambios necesarios.

## Evidencia

### Migraciones (8 nuevas, `database/migrations/2026_07_11_0000{24-31}_*.php`)

```
$ docker compose exec php php artisan migrate --force
   INFO  Running migrations.

  2026_07_11_000024_create_charge_concepts_table ..................... 6s DONE
  2026_07_11_000025_create_billing_periods_table ............... 739.11ms DONE
  2026_07_11_000026_create_billing_runs_table .................. 580.07ms DONE
  2026_07_11_000027_create_invoices_table ...................... 746.76ms DONE
  2026_07_11_000028_create_invoice_items_table ................. 617.10ms DONE
  2026_07_11_000029_create_payment_receipts_table .............. 515.72ms DONE
  2026_07_11_000030_create_payment_allocations_table ........... 282.46ms DONE
  2026_07_11_000031_create_peace_certificates_table ............ 281.72ms DONE
```

```
$ docker compose exec php php artisan migrate:rollback --step=8 --force
   INFO  Rolling back migrations.
  2026_07_11_000031_create_peace_certificates_table .................. 2s DONE
  2026_07_11_000030_create_payment_allocations_table ........... 303.48ms DONE
  2026_07_11_000029_create_payment_receipts_table .............. 408.41ms DONE
  2026_07_11_000028_create_invoice_items_table .................. 21.82ms DONE
  2026_07_11_000027_create_invoices_table ....................... 31.29ms DONE
  2026_07_11_000026_create_billing_runs_table .................. 107.62ms DONE
  2026_07_11_000025_create_billing_periods_table ................ 84.79ms DONE
  2026_07_11_000024_create_charge_concepts_table ................ 40.29ms DONE

$ docker compose exec php php artisan migrate --force
   INFO  Running migrations.
  (las 8 se re-crean sin error — confirma down() reversible)
```

### Seed de permisos

```
$ docker compose exec php php artisan db:seed --class=CobranzaPermissionsSeeder --force
   INFO  Seeding database.

# Verificación:
> Permission::where('name','billing.ver')->count()  =>  1
> Permission::where('name','like','cobranza.%')->orWhere('name','like','pagos.%')->count()  =>  10

# Re-ejecutado — idempotente, sigue en 1 (sin duplicar)
```

### Tests (Pest, 21 tests nuevos: `BillingMigrationTest` 8 + `BillingModelTest` 13)

```
$ php artisan test --filter=Billing
   PASS  Tests\Unit\Billing\BillingMigrationTest
   PASS  Tests\Unit\Billing\BillingModelTest
   Tests: 21 passed (85 assertions)
```

Cubren los criterios 1–16: reversibilidad (1-3), seed de permisos (4), relaciones `hasMany`/`belongsTo`
incluida la lectura cross-context de `Properties` vía `Invoice->property` (ADR-002) (5-7), integridad
de FK (8), `UNIQUE(billing_period_id) WHERE estado='completado'` (9), CHECK de `tipo`/`metodo_calculo`
de `charge_concepts` (10) y `medio` de `payment_receipts` (11), soft delete (12), UUID v7 (13), actor
obligatorio NOT NULL en `ejecutado_por`/`emitido_por` (14-15), permiso `billing.ver` único (16).

### `composer ci` completo (Pint + PHPStan + suite completa, incluye los 21 tests nuevos)

```
$ composer ci
{"tool":"pint","result":"passed"}
[OK] No errors (PHPStan, 219 archivos analizados)
Tests: 297 passed (996 assertions)
```

297 = 276 tests previos (baseline post `DIRECTORIO-B01` verify-council) + 21 nuevos de este bloque.
Sin regresiones.

### Hallazgos reales encontrados y corregidos durante la implementación

1. **`billing.ver` no existía realmente en el backend, pese a que `PANORAMA.md` §5 y el `Alcance` de
   esta misma tarjeta asumían que sí** ("ya existe, creado por DASHBOARD"). Verificado con
   `grep -r "billing.ver" code/api` → cero resultados antes de este bloque. Causa raíz: `DASHBOARD-B01/
   B02/B03` son los tres `proyecto: web` — nunca hubo un bloque API que sembrara el permiso, solo se
   referenciaba client-side (`QuickLinksWidget.tsx`, `getVisibleSidebar`). Resuelto sin expandir el
   alcance de la tarjeta: `CobranzaPermissionsSeeder` crea `billing.ver` de forma idempotente
   (`firstOrCreate`) junto con los 10 permisos genuinamente nuevos — el criterio de aceptación #16 (no
   duplicar) se cumple sin importar si el permiso ya existía o no. Documentado en
   `api/API_DATABASE.md` y en el propio seeder.

2. **`DirectorioMigrationTest` se rompía al agregar migraciones nuevas después de las suyas** — 3 tests
   fallaban (`contacts backfill...`, `rollback of the 3 DIRECTORIO-B01 migrations...`, `rollback fails
   loudly...`) porque usaban `migrate:rollback --step=3` para deshacer sus 3 migraciones propias,
   asumiendo implícitamente que eran siempre "las últimas 3" en el directorio plano
   `database/migrations/`. Al agregar las 8 migraciones de `COBRANZA-B01` (fechadas después,
   `2026_07_11_*`), `--step=3` pasó a apuntar a las últimas 3 de Billing en vez de las de Directorio —
   regresión real, no un fallo pre-existente enmascarado. Corregido en
   `tests/Unit/Directorio/DirectorioMigrationTest.php`: los 4 usos de `--step=3` se reemplazaron por
   `--path` apuntando explícitamente a los 3 archivos de `DIRECTORIO-B01`, inmune a cuántas migraciones
   se agreguen después. Sin este fix, **cualquier** feature futura que agregara migraciones habría
   reproducido la misma falla — no es específico de Billing. Reverificado: 297/297 tests, incluidos los
   11 de `DirectorioMigrationTest`.

### Archivos creados

- `database/migrations/2026_07_11_000024_create_charge_concepts_table.php`
- `database/migrations/2026_07_11_000025_create_billing_periods_table.php`
- `database/migrations/2026_07_11_000026_create_billing_runs_table.php`
- `database/migrations/2026_07_11_000027_create_invoices_table.php`
- `database/migrations/2026_07_11_000028_create_invoice_items_table.php`
- `database/migrations/2026_07_11_000029_create_payment_receipts_table.php`
- `database/migrations/2026_07_11_000030_create_payment_allocations_table.php`
- `database/migrations/2026_07_11_000031_create_peace_certificates_table.php`
- `src/Billing/Infrastructure/Models/EloquentChargeConcept.php`
- `src/Billing/Infrastructure/Models/EloquentBillingPeriod.php`
- `src/Billing/Infrastructure/Models/EloquentBillingRun.php`
- `src/Billing/Infrastructure/Models/EloquentInvoice.php`
- `src/Billing/Infrastructure/Models/EloquentInvoiceItem.php`
- `src/Billing/Infrastructure/Models/EloquentPaymentReceipt.php`
- `src/Billing/Infrastructure/Models/EloquentPaymentAllocation.php`
- `src/Billing/Infrastructure/Models/EloquentPeaceCertificate.php`
- `database/seeders/CobranzaPermissionsSeeder.php`
- `tests/Unit/Billing/BillingMigrationTest.php`
- `tests/Unit/Billing/BillingModelTest.php`

### Archivos modificados

- `database/seeders/DatabaseSeeder.php` — registro de `CobranzaPermissionsSeeder`.
- `tests/Unit/Directorio/DirectorioMigrationTest.php` — fix de `--step=3` → `--path` (hallazgo #2
  arriba).
- `api/API_DATABASE.md` — 8 tablas nuevas + sección de permisos RBAC de COBRANZA.
- `_state/BOARD.md` — estado del bloque.

## Notas

> Las reglas de negocio de `PANORAMA.md` §5 (R-COB-01 a R-COB-30) se implementan en los bloques de
> endpoints (`B02`-`B06`), no aquí — con la excepción estructural de las CHECK constraints y el
> `UNIQUE` parcial de `billing_runs`, que sí viven en este bloque por ser garantías de integridad de
> BD, mismo criterio que `PROPIEDADES-B01`.
>
> Este bloque depende de `DIRECTORIO-B01` porque `payment_receipts.contact_id` referencia `contacts` y
> `Invoice`/`PaymentReceipt` leen `property_occupants` indirectamente vía `Properties`/`Directorio` —
> `DIRECTORIO-B01` llegó a `done` (verify-council OK) antes de que este bloque se ejecutara.
