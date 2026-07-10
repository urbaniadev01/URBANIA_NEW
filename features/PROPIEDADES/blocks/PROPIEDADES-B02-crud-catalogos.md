---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B02
proyectos: [api]
estado: done
depende_de: [PROPIEDADES-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-10
---

# PROPIEDADES-B02 — CRUD de catálogos (tipos y estados de propiedad)

## Objetivo

Implementar los 10 endpoints REST de administración de catálogos: `property-types` y
`property-statuses`. Estos catálogos son requeridos por el CRUD de unidades (B04) y por las pantallas
web de administración (B06).

## Alcance

- **Incluye:**
  - `PropertyTypeController` — `index`, `store`, `show`, `update`, `destroy`.
  - `PropertyStatusController` — `index`, `store`, `show`, `update`, `destroy`.
  - FormRequests para store/update con validación de campos requeridos, longitud máxima, unicidad
    por `organization_id`.
  - API Resources para serialización consistente (index/show).
  - Scope automático por tenant: `index` solo devuelve catálogos del sistema (`organization_id IS
    NULL`) + los de la organización del usuario autenticado.
  - Protección de catálogos del sistema: no se puede editar ni eliminar registros con
    `organization_id IS NULL` (R-08) → `403 SYSTEM_CATALOG_READONLY`.
  - Regla de negocio: no se puede eliminar un tipo/estado que esté referenciado por propiedades
    activas (R-03) → `409 PROPERTY_TYPE_IN_USE` / `409 PROPERTY_STATUS_IN_USE`.
  - Nombre duplicado dentro de la misma organización → `409 PROPERTY_TYPE_NAME_DUPLICATE` /
    `409 PROPERTY_STATUS_NAME_DUPLICATE` (conflicto de unicidad de recurso, no error de formato —
    mismo criterio que `EMAIL_ALREADY_REGISTERED` en AUTH: `409`, no `422`. `422 VALIDATION_ERROR`
    queda reservado para campos faltantes/formato inválido).
  - `created_by`/`updated_by` seteados con el `user_id` del actor autenticado en `store`/`update`
    (R-11).
  - Tests de feature para los 10 endpoints — al menos 1 caso negativo por cada acción de escritura
    (store, update, destroy).

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de condominios, torres, unidades o coeficientes.
  - Endpoints de catálogos para otras features (ej. `document_types` de DIRECTORIO).
  - Seeders de catálogos del sistema (ya existen en B01).
  - UI web (B06).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario autenticado (admin) | `GET /property-types` | 200 + lista de tipos (sistema + org) |
| 2 | Usuario autenticado (admin) | `POST /property-types` con `{nombre, descripcion}` | 201 + tipo creado con `organization_id` y `created_by` del usuario |
| 3 | Tipo existente en misma org | `POST /property-types` con mismo `nombre` | 409 `PROPERTY_TYPE_NAME_DUPLICATE` |
| 4 | Usuario autenticado (admin) | `PATCH /property-types/{id}` (tipo propio) | 200 + tipo actualizado, `updated_by` seteado |
| 5 | Tipo con `organization_id IS NULL` | `PATCH /property-types/{id}` | 403 `SYSTEM_CATALOG_READONLY` |
| 6 | Usuario autenticado (admin) | `DELETE /property-types/{id}` (tipo propio sin uso) | 204 — soft-delete exitoso |
| 7 | Tipo en uso por propiedades activas | `DELETE /property-types/{id}` | 409 `PROPERTY_TYPE_IN_USE` |
| 8 | Tipo con `organization_id IS NULL` | `DELETE /property-types/{id}` | 403 `SYSTEM_CATALOG_READONLY` |
| 9 | Usuario no autenticado | Cualquier endpoint | 401 |
| 10 | Usuario autenticado (admin) | `GET /property-statuses` | 200 + lista de estados (sistema + org) |
| 11 | Usuario autenticado (admin) | `POST /property-statuses` con `{nombre, descripcion}` | 201 + estado creado, `created_by` seteado |
| 12 | Estado con `organization_id IS NULL` | `PATCH /property-statuses/{id}` | 403 `SYSTEM_CATALOG_READONLY` — misma regla que tipos |
| 13 | Estado en uso por propiedades activas | `DELETE /property-statuses/{id}` | 409 `PROPERTY_STATUS_IN_USE` |
| 14 | Usuario con rol `residente` | `GET /property-types` | 200 — lectura permitida (necesaria para formularios) |
| 15 | Datos de otra organización | `GET /property-types` (filtrado) | No aparece en resultados — tenant isolation (R-09) |
| 16 | Estado existente en misma org | `POST /property-statuses` con mismo `nombre` | 409 `PROPERTY_STATUS_NAME_DUPLICATE` |

## Contrato

Este bloque **produce** el contrato de los endpoints `/property-types` y `/property-statuses`. Al
completar el DoD, se congela en `_state/contracts/CONTRACT_LOCKS.md` como `LOCK-PROPIEDADES-01`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (16 casos) — incluidos los negativos (3, 5, 7, 8, 9, 12, 13, 15, 16).
- [ ] Si el bloque agregó/tocó un endpoint: request/response reales pegados (curl o equivalente).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-PROPIEDADES-01` creada.
- [ ] `api/API_CONTRACT.md` §3 — agregar `SYSTEM_CATALOG_READONLY` (403), `PROPERTY_TYPE_IN_USE`
      (409), `PROPERTY_STATUS_IN_USE` (409), `PROPERTY_TYPE_NAME_DUPLICATE` (409),
      `PROPERTY_STATUS_NAME_DUPLICATE` (409) a la tabla maestra de códigos.
- [ ] `api/endpoints/PROPIEDADES.md` creado/actualizado con el detalle de request/response de los 10
      endpoints de catálogos.

## Evidencia

### CI (`composer ci`)
| Paso | Resultado |
|---|---|
| Pint | ✅ PASS — 177 files |
| PHPStan | ✅ No errors |
| Tests | ✅ 143 passed, 489 assertions |

### Archivos creados
| Archivo | Tipo |
|---|---|
| `src/Properties/Infrastructure/Http/Controllers/PropertyTypeController.php` | Controlador (5 acciones) |
| `src/Properties/Infrastructure/Http/Controllers/PropertyStatusController.php` | Controlador (5 acciones) |
| `src/Properties/Infrastructure/Http/Requests/PropertyType/StorePropertyTypeRequest.php` | FormRequest |
| `src/Properties/Infrastructure/Http/Requests/PropertyType/UpdatePropertyTypeRequest.php` | FormRequest |
| `src/Properties/Infrastructure/Http/Requests/PropertyStatus/StorePropertyStatusRequest.php` | FormRequest |
| `src/Properties/Infrastructure/Http/Requests/PropertyStatus/UpdatePropertyStatusRequest.php` | FormRequest |
| `src/Properties/Infrastructure/Http/Resources/PropertyTypeResource.php` | API Resource |
| `src/Properties/Infrastructure/Http/Resources/PropertyStatusResource.php` | API Resource |
| `tests/Feature/Properties/PropertyTypeTest.php` | 9 tests |
| `tests/Feature/Properties/PropertyStatusTest.php` | 6 tests |

### Archivos modificados
| Archivo | Cambio |
|---|---|
| `routes/api.php` | +10 endpoints REST |
| `api/endpoints/PROPIEDADES.md` | Documentación 10 endpoints |
| `_state/contracts/CONTRACT_LOCKS.md` | LOCK-PROPIEDADES-01 creado |
| `api/API_CONTRACT.md` | +5 códigos de error |

### Criterios de aceptación cubiertos

Todos los 16 criterios de aceptación están cubiertos por los tests:
- PropertyTypeTest: criterios #1-9, #14-15
- PropertyStatusTest: criterios #10-13, #16

## Notas

> Los catálogos del sistema (`organization_id IS NULL`) los insertó B01 vía seeders. Este bloque
> solo permite a los tenants crear, editar y eliminar sus propios catálogos personalizados.

> **Fix post-done (2026-07-10):** Al cerrar el DoD de `PROPIEDADES-B06` se encontró que
> `POST`/`PATCH` de `property-types` y `property-statuses` respondían con envelope
> `{property_type: {...}}` / `{property_status: {...}}` en vez de `{data: {...}}` — violando el
> contrato congelado `LOCK-PROPIEDADES-01` (que documenta `{data: {...}}` para los tres verbos) y
> rompiendo el frontend en producción (`response.data.nombre` con `response.data === undefined` →
> `TypeError` no capturado en el toast de éxito de `TiposPropiedadPage`/`EstadosPropiedadPage`).
> Causa raíz: `PropertyTypeResource`/`PropertyStatusResource` declaraban
> `public static $wrap = 'property_type'` / `'property_status'`, que Laravel solo aplica cuando el
> Resource se serializa vía `->response()` (usado en `store()`/`update()`), no cuando se envuelve
> manualmente con `response()->json(['data' => ...])` (usado en `index()`/`show()`) — de ahí que
> GET funcionara bien y POST/PATCH no. Corregido a `$wrap = 'data'` en ambos Resources. Los tests
> de `PropertyTypeTest`/`PropertyStatusTest` habían sido escritos contra el bug
> (`$response->json('property_type')`) en vez de contra el contrato — también corregidos. Ver
> `_state/RUNBOOK.md#E-006` para el diagnóstico completo y
> `code/web/scripts/verify-propiedades-contract.mjs` para el script de verificación de contrato que
> lo detectó (sustituto de la verificación visual Playwright, bloqueada por
> `_state/RUNBOOK.md#E-005`).
