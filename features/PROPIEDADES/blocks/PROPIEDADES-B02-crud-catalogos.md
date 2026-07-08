---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B02
proyectos: [api]
estado: backlog
depende_de: [PROPIEDADES-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-06
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
    `organization_id IS NULL` (R-08).
  - Regla de negocio: no se puede eliminar un tipo/estado que esté referenciado por propiedades
    activas (R-03).
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
| 2 | Usuario autenticado (admin) | `POST /property-types` con `{nombre, descripcion}` | 201 + tipo creado con `organization_id` del usuario |
| 3 | Tipo existente en misma org | `POST /property-types` con mismo `nombre` | 422 — error de unicidad |
| 4 | Usuario autenticado (admin) | `PATCH /property-types/{id}` (tipo propio) | 200 + tipo actualizado |
| 5 | Tipo con `organization_id IS NULL` | `PATCH /property-types/{id}` | 403 — no se puede editar catálogo del sistema |
| 6 | Usuario autenticado (admin) | `DELETE /property-types/{id}` (tipo propio sin uso) | 204 — soft-delete exitoso |
| 7 | Tipo en uso por propiedades activas | `DELETE /property-types/{id}` | 409 — no se puede eliminar, tiene propiedades activas |
| 8 | Tipo con `organization_id IS NULL` | `DELETE /property-types/{id}` | 403 — no se puede eliminar catálogo del sistema |
| 9 | Usuario no autenticado | Cualquier endpoint | 401 |
| 10 | Usuario autenticado (admin) | `GET /property-statuses` | 200 + lista de estados (sistema + org) |
| 11 | Usuario autenticado (admin) | `POST /property-statuses` con `{nombre, descripcion}` | 201 + estado creado |
| 12 | Estado con `organization_id IS NULL` | `PATCH /property-statuses/{id}` | 403 — misma regla que tipos |
| 13 | Estado en uso por propiedades activas | `DELETE /property-statuses/{id}` | 409 — misma regla que tipos |
| 14 | Usuario con rol `residente` | `GET /property-types` | 200 — lectura permitida (necesaria para formularios) |
| 15 | Datos de otra organización | `GET /property-types` (filtrado) | No aparece en resultados — tenant isolation (R-09) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/property-types` y `/property-statuses`. Al
completar el DoD, se congela en `_state/contracts/CONTRACT_LOCKS.md` como `LOCK-PROPIEDADES-01`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (15 casos) — incluidos los negativos (3, 5, 7, 8, 9, 12, 13, 15).
- [ ] Si el bloque agregó/tocó un endpoint: request/response reales pegados (curl o equivalente).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-PROPIEDADES-01` creada.
- [ ] `api/API_CONTRACT.md` actualizado con los nuevos endpoints y códigos de error.
- [ ] `api/endpoints/PROPIEDADES.md` creado/actualizado con el detalle de request/response de los 10
      endpoints de catálogos.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Los catálogos del sistema (`organization_id IS NULL`) los insertó B01 vía seeders. Este bloque
> solo permite a los tenants crear, editar y eliminar sus propios catálogos personalizados.
