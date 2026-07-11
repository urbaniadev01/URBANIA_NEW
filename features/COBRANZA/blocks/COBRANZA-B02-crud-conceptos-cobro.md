---
tipo: bloque
proyecto: api
feature: COBRANZA
id: COBRANZA-B02
proyectos: [api]
estado: done
depende_de: [COBRANZA-B01]
contrato: null
verificacion_critica: false
actualizado: 2026-07-09
---

# COBRANZA-B02 — CRUD de conceptos de cobro

## Objetivo

Exponer el CRUD completo de `charge_concepts` (crear, listar, ver, editar, desactivar), primer
endpoint del feature y base sobre la que corre la facturación (`COBRANZA-B03`). Establece el patrón
de autorización (`cobranza.conceptos.ver`/`cobranza.conceptos.gestionar`) y el `warnings[]` de
R-COB-18 que bloques posteriores reutilizan.

## Alcance

- **Incluye:**
  - `GET /condominiums/{id}/charge-concepts` — listado, scope `condominium_id` (R-COB-01).
  - `GET /charge-concepts/{id}` — detalle.
  - `POST /condominiums/{id}/charge-concepts` — creación. Valida `UNIQUE(condominium_id, nombre)
    WHERE deleted_at IS NULL`, `tipo`/`metodo_calculo` dentro del set cerrado (defensa de aplicación,
    la BD ya lo garantiza vía CHECK de `COBRANZA-B01`). Si `tipo = fondo_imprevistos`, la respuesta
    incluye `warnings: [{code: "FONDO_IMPREVISTOS_VALIDACION_PENDIENTE", ...}]` (R-COB-18).
  - `PATCH /charge-concepts/{id}` — edición.
  - `DELETE /charge-concepts/{id}` — desactivación (soft delete; `activo = false` se setea junto con
    el borrado para que quede claro en queries sin `withTrashed`).
  - Middleware RBAC con los permisos `cobranza.conceptos.ver`/`cobranza.conceptos.gestionar` — primer
    uso real de los 11 permisos seedeados en `COBRANZA-B01`. Asignar `cobranza.conceptos.*` al rol de
    sistema "Administrador de conjunto" (mismo criterio de asignación que `PROPIEDADES-B02` usó para
    sus permisos de catálogo).
  - `api/endpoints/COBRANZA.md` — creación del documento de detalle request/response (no existe
    todavía; ver `api/endpoints/PROPIEDADES.md` como formato de referencia).

- **No incluye (explícitamente fuera de este bloque):**
  - `billing_periods`, `billing_runs` — `COBRANZA-B03`.
  - Validación real del mínimo legal de 1% para `fondo_imprevistos` — diferida (R-COB-18), este
    bloque solo emite el warning.
  - R-COB-29 (advertencia de conceptos `extraordinaria` duplicados) — es responsabilidad de la
    pantalla Web (`COBRANZA-B07`), no de este endpoint: el API no impone unicidad, solo la UI muestra
    los existentes al crear.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `cobranza.conceptos.ver`, condominio con conceptos | `GET /condominiums/{id}/charge-concepts` | `200`, lista scopeada al condominio |
| 2 | Usuario con `cobranza.conceptos.gestionar` | `POST .../charge-concepts` con `tipo=administracion`, `metodo_calculo=coeficiente` | `201`, concepto creado |
| 3 | Igual que #2 pero `tipo=fondo_imprevistos` | `POST .../charge-concepts` | `201` con `warnings: [{code: "FONDO_IMPREVISTOS_VALIDACION_PENDIENTE"}]` en el body |
| 4 | Usuario con `cobranza.conceptos.gestionar` | `POST .../charge-concepts` con `nombre` duplicado en el mismo condominio | `422` con error de unicidad |
| 5 | Usuario con `cobranza.conceptos.gestionar` | `POST .../charge-concepts` con `tipo=interes` (fuera del set cerrado) | `422` — validación de aplicación, nunca llega a la BD |
| 6 | Usuario **sin** `cobranza.conceptos.ver` | `GET /condominiums/{id}/charge-concepts` | `403` |
| 7 | Usuario con `cobranza.conceptos.ver` (sin `.gestionar`) | `POST .../charge-concepts` | `403` — segregación ver/gestionar |
| 8 | Usuario de otro condominio con `cobranza.conceptos.ver` | `GET /condominiums/{otro-id}/charge-concepts` | `403` — R-COB-02, scope de staff no cubre ese condominio |
| 9 | Usuario con `cobranza.conceptos.gestionar` | `DELETE /charge-concepts/{id}` | `204`, `deleted_at` poblado, `activo = false` |
| 10 | Concepto desactivado | `GET /condominiums/{id}/charge-concepts` (sin `?incluir_inactivos`) | El concepto desactivado no aparece en el listado por defecto |

## Contrato

Este bloque **produce** contrato — al llegar a `done`, se crea `LOCK-COBRANZA-02` en
`_state/contracts/CONTRACT_LOCKS.md` para los 5 endpoints de conceptos de cobro, consumido por
`COBRANZA-B07`.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] Verificación funcional real (curl/httpie) cubriendo los 10 criterios de aceptación, incluidos
      los negativos (#4-8) — request/response reales pegados.
- [x] `LOCK-COBRANZA-02` creado en `_state/contracts/CONTRACT_LOCKS.md`.
- [x] `api/API_CONTRACT.md` actualizado con los 5 endpoints nuevos (+ 4 códigos de error + 1 warning).
- [x] `api/endpoints/COBRANZA.md` creado con el detalle request/response de estos 5 endpoints.

## Evidencia

### Implementación

- **Modelos/migraciones:** reutiliza `EloquentChargeConcept` de `COBRANZA-B01` (sin cambios de
  esquema).
- **`ChargeConceptController`** (`src/Billing/Infrastructure/Http/Controllers/`): 5 acciones
  (index/store/show/update/destroy). Autorización resuelta a mano (`hasChargeConceptsPermission()`)
  en vez del middleware genérico `require_permission` — `CheckPermissionUseCase` exige coincidencia
  exacta de `scope_type` (no expande `organization` a `condominium` pese a lo que su propio docblock
  afirma), mismo motivo por el que `PropertyController` (`LOCK-PROPIEDADES-03`) ya resuelve su scope
  a mano en vez de usar ese middleware. `ChargeConceptController` sigue el mismo patrón.
- **FormRequests**: `StoreChargeConceptRequest`/`UpdateChargeConceptRequest` — validan `tipo`/
  `metodo_calculo` contra el set cerrado (defensa de aplicación; la BD ya lo garantiza vía CHECK de
  `COBRANZA-B01`).
- **`ChargeConceptResource`**: wrap `data`, incluye `activo` (bool) y `valor_base` (float).
- **`CobranzaPermissionsSeeder`** extendido: asigna `cobranza.conceptos.ver`/`.gestionar` a los roles
  `admin` y `manager` (`syncWithoutDetaching`, idempotente) — mismo criterio que `admin.access` en
  `RbacDemoSeeder`, donde ambos roles reciben los mismos permisos operativos.
- **Rutas**: `GET/POST /condominiums/{condominium}/charge-concepts` (anidadas) +
  `GET/PATCH/DELETE /charge-concepts/{charge_concept}` (planas) — mismo patrón que
  `properties`/`property-occupants`.

### Tests de feature (13 tests, `tests/Feature/Billing/ChargeConceptTest.php`)

```
$ php artisan test tests/Feature/Billing/ChargeConceptTest.php
PASS  Tests\Feature\Billing\ChargeConceptTest
✓ list charge concepts returns 200 scoped to the condominium
✓ create charge concept returns 201
✓ create fondo_imprevistos charge concept returns warnings
✓ duplicate charge concept name returns 409
✓ charge concept with tipo outside the closed set returns 422
✓ user without cobranza.conceptos.ver gets 403 on list
✓ user with only cobranza.conceptos.ver gets 403 on create
✓ user scoped to a different condominium gets 403
✓ delete charge concept returns 204 and deactivates it
✓ deactivated concept does not appear in the default listing
✓ show and update work for a concept within the same organization
✓ charge concept from another organization returns 404
✓ unauthenticated access returns 401

Tests: 13 passed (39 assertions)
```

Cubre los 10 criterios de aceptación de la tarjeta (CA1-CA10) más 2 casos adicionales (tenant
isolation en `show`, autenticación).

### `composer ci` completo

```
$ composer ci
{"tool":"pint","result":"passed"}
[OK] No errors (PHPStan, 223 archivos)
Tests: 310 passed (1034 assertions)
```

310 = 297 previos (post `COBRANZA-B01`) + 13 nuevos. Sin regresiones.

### Verificación funcional real (curl, servidor Docker real — `admin@urbania.test` + usuarios ad-hoc)

```
=== CA1: GET listado (200, scopeado) ===
HTTP:200 — {"data":[...]}

=== CA2: POST administracion (201, activo:true) ===
HTTP:201 — {"data":{"nombre":"Administracion Curl 2",...,"activo":true,...}}

=== CA3: POST fondo_imprevistos (201 + warnings) ===
HTTP:201 — {"data":{...},"warnings":[{"code":"FONDO_IMPREVISTOS_VALIDACION_PENDIENTE",...}]}

=== CA4: POST nombre duplicado (409) ===
HTTP:409 — {"error":{"code":"CHARGE_CONCEPT_NAME_DUPLICATE",...}}

=== CA5: POST tipo invalido (422) ===
HTTP:422 — {"error":{"code":"VALIDATION_ERROR","message":"El tipo debe ser uno de: ..."}}

=== CA6: usuario sin cobranza.conceptos.ver -> GET listado (403) ===
HTTP:403 — {"error":{"code":"PERMISSION_DENIED",...}}

=== CA7: usuario con .ver (sin .gestionar) -> POST (403); GET (200) ===
HTTP:403 — {"error":{"code":"PERMISSION_DENIED","message":"No tiene permisos para gestionar..."}}
HTTP:200 — (el mismo usuario sí puede listar)

=== CA8: manager escopeado a otro condominio (403); propio condominio (200) ===
HTTP:403 — {"error":{"code":"PERMISSION_DENIED",...}}
HTTP:200 — (control: en su propio condominio sí puede)

=== CA9: DELETE (204); GET posterior (404) ===
HTTP:204
HTTP:404 — {"error":{"code":"CHARGE_CONCEPT_NOT_FOUND",...}}

=== CA10: listado tras desactivar uno (no aparece) ===
HTTP:200 — {"data":[...]} (2 de 3 creados, el desactivado no aparece)
```

Datos de prueba (usuarios/roles ad-hoc, conceptos de prueba) limpiados de la BD de desarrollo tras la
verificación.

### Bug real encontrado y corregido durante la verificación

**`POST` devolvía `activo: null` en vez de `true`.** El controller retornaba el modelo recién creado
sin refrescarlo (`$concept->save()` seguido de `new ChargeConceptResource($concept)`), así que el
`DEFAULT true` de la columna `activo` (aplicado por Postgres al insertar) nunca se reflejaba en el
objeto PHP en memoria — un `GET` inmediatamente después sí mostraba `activo: true` correctamente.
Corregido devolviendo `$concept->fresh()` en `store()` (mismo patrón que `update()` ya usaba). Test de
regresión agregado (`create charge concept returns 201` ahora afirma `activo === true`).

### Hallazgo de entorno (no es un bug de este bloque)

Las requests HTTP sin header `Accept: application/json` (ej. `curl` sin ese header) contra **cualquier**
endpoint `auth:api` sin token disparan `RouteNotFoundException: Route [login] not defined` (Laravel
intenta redirigir a una ruta `login` que no existe en esta API-only app) en vez de un `401` limpio —
confirmado también en `/property-types` (endpoint preexistente, ajeno a este bloque). Con
`Accept: application/json` responde `401` limpio e instantáneo, que es como se comporta el frontend
real (siempre envía ese header) y como lo verifica el test automatizado (`getJson()` de Pest ya lo
envía). No se corrigió por estar fuera de alcance de `COBRANZA-B02` — es un comportamiento transversal
de toda la API, no específico de `charge-concepts`.

### Archivos creados

- `src/Billing/Infrastructure/Http/Controllers/ChargeConceptController.php`
- `src/Billing/Infrastructure/Http/Requests/ChargeConcept/StoreChargeConceptRequest.php`
- `src/Billing/Infrastructure/Http/Requests/ChargeConcept/UpdateChargeConceptRequest.php`
- `src/Billing/Infrastructure/Http/Resources/ChargeConceptResource.php`
- `tests/Feature/Billing/ChargeConceptTest.php`
- `api/endpoints/COBRANZA.md`

### Archivos modificados

- `routes/api.php` — 5 endpoints nuevos.
- `database/seeders/CobranzaPermissionsSeeder.php` — asignación de permisos a roles `admin`/`manager`.
- `_state/contracts/CONTRACT_LOCKS.md` — `LOCK-COBRANZA-02` creado.
- `api/API_CONTRACT.md` — 4 códigos de error + 1 warning nuevos.
- `_state/BOARD.md` — estado del bloque.

## Notas

> Este bloque decide el formato exacto de `warnings[]` en el body de respuesta (R-COB-18) — los
> bloques `COBRANZA-B03` (R-COB-08-bis) y `COBRANZA-B05`/`B06` reutilizan el mismo mecanismo, no
> inventan uno nuevo. Documentar el formato en `api/API_CONTRACT.md` como convención general, mismo
> criterio que R-COB-22 pide para el patrón 202+polling.
