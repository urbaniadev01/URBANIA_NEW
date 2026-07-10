---
tipo: bloque
proyecto: api
feature: DIRECTORIO
id: DIRECTORIO-B02
proyectos: [api]
estado: backlog
depende_de: [DIRECTORIO-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-08
---

# DIRECTORIO-B02 — CRUD de catálogo (tipos de ocupante)

## Objetivo

Implementar los 5 endpoints REST de administración del catálogo `occupant-types`. Requerido por la
asignación de ocupantes (`DIRECTORIO-B04`) y por la pantalla web de administración (`DIRECTORIO-B05`).
Mismo patrón exacto que `PROPIEDADES-B02` (catálogos de tipos/estados de propiedad).

## Alcance

- **Incluye:**
  - `OccupantTypeController` — `index`, `store`, `show`, `update`, `destroy`.
  - FormRequests para store/update con validación de campos requeridos, longitud máxima, unicidad
    por `organization_id` → `409 OCCUPANT_TYPE_NAME_DUPLICATE` (conflicto de recurso, no `422` —
    mismo criterio que `PROPERTY_TYPE_NAME_DUPLICATE` en `PROPIEDADES-B02`).
  - API Resources para serialización consistente (index/show).
  - Scope automático por tenant: `index` solo devuelve catálogo de sistema (`organization_id IS
    NULL`) + los de la organización del usuario autenticado.
  - Protección de catálogo de sistema: no se puede editar ni eliminar registros con
    `organization_id IS NULL` (R-DIR-09) → `403 SYSTEM_CATALOG_READONLY` (mismo `code` ya usado en
    `PROPIEDADES-B02` — es la misma regla aplicada a un catálogo distinto, se reutiliza el código en
    vez de crear uno nuevo).
  - Regla de negocio: no se puede eliminar un tipo de ocupante referenciado por `property_occupants`
    activos → `409 OCCUPANT_TYPE_IN_USE`.
  - `created_by`/`updated_by` seteados con el `user_id` del actor autenticado en `store`/`update`
    (R-DIR-10).
  - Tests de feature para los 5 endpoints — al menos 1 caso negativo por cada acción de escritura.

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de contactos (`DIRECTORIO-B03`) o asignación de ocupantes (`DIRECTORIO-B04`).
  - Seeders del catálogo de sistema (ya existen en `DIRECTORIO-B01`).
  - UI web (`DIRECTORIO-B05`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario autenticado (admin) | `GET /occupant-types` | 200 + lista de tipos (sistema + org) |
| 2 | Usuario autenticado (admin) | `POST /occupant-types` con `{nombre, descripcion}` | 201 + tipo creado con `organization_id` y `created_by` del usuario |
| 3 | Tipo existente en misma org | `POST /occupant-types` con mismo `nombre` | 409 `OCCUPANT_TYPE_NAME_DUPLICATE` |
| 4 | Usuario autenticado (admin) | `PATCH /occupant-types/{id}` (tipo propio) | 200 + tipo actualizado, `updated_by` seteado |
| 5 | Tipo con `organization_id IS NULL` | `PATCH /occupant-types/{id}` | 403 `SYSTEM_CATALOG_READONLY` |
| 6 | Usuario autenticado (admin) | `DELETE /occupant-types/{id}` (tipo propio sin uso) | 204 — soft-delete exitoso |
| 7 | Tipo en uso por `property_occupants` activos | `DELETE /occupant-types/{id}` | 409 `OCCUPANT_TYPE_IN_USE` |
| 8 | Tipo con `organization_id IS NULL` | `DELETE /occupant-types/{id}` | 403 `SYSTEM_CATALOG_READONLY` |
| 9 | Usuario no autenticado | Cualquier endpoint | 401 |
| 10 | Usuario con rol `residente` | `GET /occupant-types` | 200 — lectura permitida (necesaria para formularios de autoservicio) |
| 11 | Datos de otra organización | `GET /occupant-types` (filtrado) | No aparece en resultados — tenant isolation (R-DIR-01) |
| 12 | Staff con `role_assignment.scope_type=condominium` en condominio A | `POST /occupant-types` | 201 — el catálogo es a nivel organización, no de condominio; el scope de condominio/torre no restringe la gestión de este catálogo (a diferencia de contactos/ocupantes en B03/B04) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/occupant-types`. Al completar el DoD, se
congela en `_state/contracts/CONTRACT_LOCKS.md` como `LOCK-DIRECTORIO-01`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (12 casos) — incluidos los negativos (3, 5, 7, 8, 9, 11).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-DIRECTORIO-01` creada.
- [ ] `api/API_CONTRACT.md` §3 — agregar `OCCUPANT_TYPE_NAME_DUPLICATE` (409), `OCCUPANT_TYPE_IN_USE`
      (409) a la tabla maestra de códigos (`SYSTEM_CATALOG_READONLY` ya existe desde
      `PROPIEDADES-B02`, se reutiliza sin duplicar).
- [ ] `api/endpoints/DIRECTORIO.md` creado con el detalle de request/response de los 5 endpoints de
      catálogo.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> `SYSTEM_CATALOG_READONLY` se reutiliza del catálogo de `PROPIEDADES-B02` — mismo significado exacto
> (catálogo de sistema, `organization_id IS NULL`, no editable por tenants), no hace falta un `code`
> nuevo por feature cuando la regla es idéntica.
