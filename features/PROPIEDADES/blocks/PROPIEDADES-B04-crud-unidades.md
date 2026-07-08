---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B04
proyectos: [api]
estado: backlog
depende_de: [PROPIEDADES-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-06
---

# PROPIEDADES-B04 — CRUD de unidades (properties)

## Objetivo

Implementar los endpoints REST de gestión de unidades (`properties`): listado con filtros, creación,
detalle, edición y eliminación. Es el corazón del dominio de PROPIEDADES — las unidades son la
entidad que referencian DIRECTORIO, COBRANZA y PORTERIA.

## Alcance

- **Incluye:**
  - `PropertyController` — `index` (anidado bajo condominium:
    `GET /condominiums/{id}/properties`) con filtros `tower_id`, `type_id`, `status_id`, `search`;
    `store` anidado; `show`; `update`; `destroy`.
  - FormRequests para store/update con validación: `codigo` único por `condominium_id` (R-02),
    referencias válidas a `condominium_id`, `tower_id`, `property_type_id`, `property_status_id`.
  - Inmutabilidad de `condominium_id` (R-07): no se expone en `update`.
  - Exposición diferenciada de datos (R-10): `area_m2` aparece en `show` (PropertyResource detalle)
    pero **no** en `index` (PropertyListResource resumido).
  - Regla de negocio: no se puede eliminar una unidad con ocupantes activos (R-03, verificando
    `property_occupants` de DIRECTORIO — si la tabla no existe aún, se verifica con un guard clause
    preparado para cuando exista).
  - Filtros combinables en index con paginación y scope por tenant (R-09).
  - Autorización: residente solo ve su propia unidad (derivado de `property_occupants`, R-10);
    endpoint de listado denegado para residentes (403).
  - Tests de feature para todos los endpoints — al menos 1 caso negativo por acción de escritura.

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de coeficientes (B05), condominios (B03), catálogos (B02).
  - Gestión de ocupantes (`property_occupants`) — pertenece a DIRECTORIO.
  - Endpoint tree (B05).
  - UI web de unidades (B08).
  - Importación masiva (batch import CSV/Excel) — punto ciego documentado en PANORAMA §X.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin autenticado, condominio con unidades | `GET /condominiums/{id}/properties` | 200 + lista paginada, sin `area_m2` en cada item |
| 2 | Admin, con filtro `?tower_id=X` | `GET /condominiums/{id}/properties?tower_id=X` | 200 + solo unidades de esa torre |
| 3 | Admin, con filtro `?search=A-201` | `GET /condominiums/{id}/properties?search=A-201` | 200 + unidades cuyo `codigo` contiene "A-201" |
| 4 | Admin, filtros combinados `?tower_id=X&type_id=Y&status_id=Z` | `GET /condominiums/{id}/properties?...` | 200 + intersección de los 3 filtros |
| 5 | Admin autenticado | `POST /condominiums/{id}/properties` con datos válidos | 201 + unidad creada |
| 6 | Unidad existente en mismo condominio | `POST .../properties` con mismo `codigo` | 422 — código duplicado (R-02) |
| 7 | `tower_id` de otro condominio | `POST .../properties` | 422 — la torre no pertenece a este condominio |
| 8 | Admin autenticado | `GET /properties/{id}` (propia) | 200 + detalle **con** `area_m2` |
| 9 | Admin autenticado | `PATCH /properties/{id}` | 200 + unidad actualizada |
| 10 | `PATCH /properties/{id}` incluyendo `condominium_id` | `PATCH /properties/{id}` | El campo `condominium_id` se ignora (inmutable, R-07) |
| 11 | Unidad con ocupantes activos | `DELETE /properties/{id}` | 409 — no se puede eliminar |
| 12 | Unidad sin ocupantes | `DELETE /properties/{id}` | 204 — soft-delete exitoso |
| 13 | Usuario no autenticado | Cualquier endpoint | 401 |
| 14 | Usuario de otra org | `GET /properties/{id}` (ajena) | 404 — unificado con 403 (R-10) |
| 15 | Usuario con rol `residente` | `GET /condominiums/{id}/properties` | 403 — residente no lista unidades |
| 16 | Usuario con rol `residente` | `GET /properties/{id}` (su unidad) | 200 — ve solo su propia unidad |
| 17 | Usuario con rol `residente` | `GET /properties/{id}` (unidad ajena) | 404 — unificado con 403 (R-10) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/properties` y
`/condominiums/{id}/properties`. Al completar el DoD, se congela en
`_state/contracts/CONTRACT_LOCKS.md` como `LOCK-PROPIEDADES-03`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (17 casos) — incluidos los negativos (6, 7, 10, 11, 13, 14, 15, 17).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-PROPIEDADES-03` creada.
- [ ] `api/API_CONTRACT.md` actualizado con los nuevos endpoints y códigos de error.
- [ ] `api/endpoints/PROPIEDADES.md` actualizado con el detalle de request/response de los endpoints
      de unidades.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> La verificación de ocupantes activos (criterio 11) depende de la tabla `property_occupants` que
> pertenece a DIRECTORIO. Si la tabla no existe aún, se implementa un guard clause que asume "sin
> ocupantes" con un `@todo` explícito para cuando DIRECTORIO esté disponible. No se bloquea el
> desarrollo de PROPIEDADES por una dependencia futura.
