---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B03
proyectos: [api]
estado: done
depende_de: [PROPIEDADES-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-08
---

# PROPIEDADES-B03 — CRUD de condominios y torres

## Objetivo

Implementar los endpoints REST para gestionar condominios y sus torres anidadas. Los condominios son
la raíz de la jerarquía de propiedades (R-01); sin este bloque, no se pueden crear unidades (B04) ni
visualizar la estructura en web (B07).

## Alcance

- **Incluye:**
  - `CondominiumController` — `index`, `store`, `show`, `update`, `destroy`.
  - `TowerController` — `index` anidado bajo condominium (`/condominiums/{id}/towers`), `store`
    anidado, `show`, `update`, `destroy`.
  - FormRequests para store/update con validación de unicidad: nombre de condominio único por
    `organization_id` (409 `CONDOMINIUM_NAME_DUPLICATE`), nombre de torre único por `condominium_id`
    (409 `TOWER_NAME_DUPLICATE`) — conflicto de recurso, no `422` (mismo criterio que
    `EMAIL_ALREADY_REGISTERED` en AUTH).
  - Inmutabilidad de `condominium_id` en torres (R-07): el campo no se expone en `update`.
  - Regla de negocio: no se puede eliminar un condominio con torres activas (R-03) → `409
    CONDOMINIUM_HAS_TOWERS`. No se puede eliminar un condominio con propiedades activas → `409
    CONDOMINIUM_HAS_PROPERTIES`. No se puede eliminar una torre con propiedades activas → `409
    TOWER_HAS_PROPERTIES`.
  - Soft delete en ambas entidades (R-04).
  - Scope automático por tenant (R-09): `index` de condominios solo devuelve los de la organización
    del usuario.
  - Scope automático por asignación de staff (R-09-bis): un usuario con `role_assignment.scope_type
    = condominium` solo ve/gestiona el condominio de su(s) scope(s), aunque haya otros condominios
    en la misma organización. Mismo criterio anti-enumeración de R-10 (403/404 unificados) para
    condominios fuera de su scope.
  - `created_by`/`updated_by` seteados con el `user_id` del actor autenticado en `store`/`update` de
    ambas entidades (R-11).
  - API Resources para serialización consistente, con inclusión condicional de `towers` en `show`.
  - Tests de feature para los 10 endpoints — al menos 1 caso negativo por cada acción de escritura.

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de unidades (B04), coeficientes (B05), catálogos (B02).
  - Endpoint tree (`/condominiums/{id}/tree`) — va en B05.
  - UI web de condominios (B07).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario autenticado (admin) | `GET /condominiums` | 200 + lista de condominios de su org |
| 2 | Usuario autenticado (admin) | `POST /condominiums` con `{nombre, direccion, nit}` | 201 + condominio creado, `created_by` seteado |
| 3 | Condominio existente en misma org | `POST /condominiums` con mismo `nombre` | 409 `CONDOMINIUM_NAME_DUPLICATE` |
| 4 | Usuario autenticado (admin) | `GET /condominiums/{id}` (propio) | 200 + detalle con towers |
| 5 | Usuario autenticado (admin) | `PATCH /condominiums/{id}` | 200 + condominio actualizado, `updated_by` seteado |
| 6 | Condominio con torres activas | `DELETE /condominiums/{id}` | 409 `CONDOMINIUM_HAS_TOWERS` |
| 7 | Condominio con propiedades activas | `DELETE /condominiums/{id}` | 409 `CONDOMINIUM_HAS_PROPERTIES` |
| 8 | Condominio sin hijos | `DELETE /condominiums/{id}` | 204 — soft-delete exitoso |
| 9 | Usuario autenticado (admin) | `GET /condominiums/{id}/towers` | 200 + lista de torres del condominio |
| 10 | Usuario autenticado (admin) | `POST /condominiums/{id}/towers` con `{nombre}` | 201 + torre creada, `created_by` seteado |
| 11 | Torre existente en mismo condominio | `POST /condominiums/{id}/towers` con mismo `nombre` | 409 `TOWER_NAME_DUPLICATE` |
| 12 | Usuario autenticado (admin) | `PATCH /towers/{id}` | 200 + torre actualizada, `updated_by` seteado |
| 13 | `PATCH /towers/{id}` incluyendo `condominium_id` | `PATCH /towers/{id}` | El campo `condominium_id` se ignora (inmutable, R-07) |
| 14 | Torre con propiedades activas | `DELETE /towers/{id}` | 409 `TOWER_HAS_PROPERTIES` |
| 15 | Torre sin propiedades | `DELETE /towers/{id}` | 204 — soft-delete exitoso |
| 16 | Usuario no autenticado | Cualquier endpoint | 401 |
| 17 | Usuario de otra org | `GET /condominiums/{id}` (ajeno) | 404 — unificado con 403 para anti-enumeración (R-10) |
| 18 | Usuario con rol `residente` | `GET /condominiums` | 403 — residente no puede listar condominios (R-10) |
| 19 | Staff con `role_assignment.scope_type=condominium` en condominio A, misma org tiene condominio B | `GET /condominiums/{B_id}` | 404 — unificado con 403, fuera de su scope (R-09-bis) |
| 20 | Staff con `role_assignment.scope_type=tower` en torre X | `PATCH /towers/{Y_id}` (otra torre del mismo condominio) | 404 — unificado con 403, fuera de su scope (R-09-bis) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/condominiums` y `/towers`. Al completar el
DoD, se congela en `_state/contracts/CONTRACT_LOCKS.md` como `LOCK-PROPIEDADES-02`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (20 casos) — incluidos los negativos (3, 6, 7, 11, 13, 14, 16, 17, 18, 19, 20).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-PROPIEDADES-02` creada.
- [ ] `api/API_CONTRACT.md` §3 — agregar `CONDOMINIUM_NAME_DUPLICATE` (409), `TOWER_NAME_DUPLICATE`
      (409), `CONDOMINIUM_HAS_TOWERS` (409), `CONDOMINIUM_HAS_PROPERTIES` (409),
      `TOWER_HAS_PROPERTIES` (409) a la tabla maestra de códigos.
- [ ] `api/endpoints/PROPIEDADES.md` actualizado con el detalle de request/response de los endpoints
      de condominios y torres.

## Evidencia

### CI (`composer ci`)
| Paso | Resultado |
|---|---|
| Pint | ✅ PASS — 187 files |
| PHPStan | ✅ No errors — 165 files |
| Tests | ✅ 163 passed, 558 assertions |

### Archivos creados
| Archivo | Tipo |
|---|---|
| `src/Properties/Infrastructure/Http/Controllers/CondominiumController.php` | Controlador (5 acciones) |
| `src/Properties/Infrastructure/Http/Controllers/TowerController.php` | Controlador (5 acciones) |
| `src/Properties/Infrastructure/Http/Requests/Condominium/StoreCondominiumRequest.php` | FormRequest |
| `src/Properties/Infrastructure/Http/Requests/Condominium/UpdateCondominiumRequest.php` | FormRequest |
| `src/Properties/Infrastructure/Http/Requests/Tower/StoreTowerRequest.php` | FormRequest |
| `src/Properties/Infrastructure/Http/Requests/Tower/UpdateTowerRequest.php` | FormRequest |
| `src/Properties/Infrastructure/Http/Resources/CondominiumResource.php` | API Resource |
| `src/Properties/Infrastructure/Http/Resources/TowerResource.php` | API Resource |
| `tests/Feature/Properties/CondominiumTest.php` | Tests |
| `tests/Feature/Properties/TowerTest.php` | Tests |

### Archivos modificados
| Archivo | Cambio |
|---|---|
| `routes/api.php` | +10 endpoints REST |
| `api/endpoints/PROPIEDADES.md` | Actualizado |
| `_state/contracts/CONTRACT_LOCKS.md` | LOCK-PROPIEDADES-02 creado |
| `api/API_CONTRACT.md` | +8 códigos de error |

## Notas

> La regla de anti-enumeración (403 y 404 unificados, R-10) aplica a `show` de condominio ajeno y a
> `show` de torre de condominio ajeno. El middleware de tenant isolation debe estar activo desde AUTH.
>
> Los criterios 19-20 (R-09-bis) son el motivo original por el que `towers` es una entidad separada
> (ver PANORAMA, Divergencias resueltas). Si el `role_assignment` del usuario autenticado no tiene
> registros de scope `condominium`/`tower` para probar estos casos, es un bloqueante de este bloque —
> no se puede dar por `done` sin al menos un fixture de staff scoped.
