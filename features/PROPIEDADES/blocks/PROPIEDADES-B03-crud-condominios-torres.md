---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B03
proyectos: [api]
estado: backlog
depende_de: [PROPIEDADES-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-06
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
    `organization_id`, nombre de torre único por `condominium_id`.
  - Inmutabilidad de `condominium_id` en torres (R-07): el campo no se expone en `update`.
  - Regla de negocio: no se puede eliminar un condominio con torres o propiedades activas (R-03) →
    409. No se puede eliminar una torre con propiedades activas → 409.
  - Soft delete en ambas entidades (R-04).
  - Scope automático por tenant (R-09): `index` de condominios solo devuelve los de la organización
    del usuario.
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
| 2 | Usuario autenticado (admin) | `POST /condominiums` con `{nombre, direccion, nit}` | 201 + condominio creado |
| 3 | Condominio existente en misma org | `POST /condominiums` con mismo `nombre` | 422 — nombre duplicado |
| 4 | Usuario autenticado (admin) | `GET /condominiums/{id}` (propio) | 200 + detalle con towers |
| 5 | Usuario autenticado (admin) | `PATCH /condominiums/{id}` | 200 + condominio actualizado |
| 6 | Condominio con torres activas | `DELETE /condominiums/{id}` | 409 — no se puede eliminar, tiene torres |
| 7 | Condominio con propiedades activas | `DELETE /condominiums/{id}` | 409 — no se puede eliminar, tiene propiedades |
| 8 | Condominio sin hijos | `DELETE /condominiums/{id}` | 204 — soft-delete exitoso |
| 9 | Usuario autenticado (admin) | `GET /condominiums/{id}/towers` | 200 + lista de torres del condominio |
| 10 | Usuario autenticado (admin) | `POST /condominiums/{id}/towers` con `{nombre}` | 201 + torre creada |
| 11 | Torre existente en mismo condominio | `POST /condominiums/{id}/towers` con mismo `nombre` | 422 — nombre duplicado |
| 12 | Usuario autenticado (admin) | `PATCH /towers/{id}` | 200 + torre actualizada |
| 13 | `PATCH /towers/{id}` incluyendo `condominium_id` | `PATCH /towers/{id}` | El campo `condominium_id` se ignora (inmutable, R-07) |
| 14 | Torre con propiedades activas | `DELETE /towers/{id}` | 409 — no se puede eliminar, tiene propiedades |
| 15 | Torre sin propiedades | `DELETE /towers/{id}` | 204 — soft-delete exitoso |
| 16 | Usuario no autenticado | Cualquier endpoint | 401 |
| 17 | Usuario de otra org | `GET /condominiums/{id}` (ajeno) | 404 — unificado con 403 para anti-enumeración (R-10) |
| 18 | Usuario con rol `residente` | `GET /condominiums` | 403 — residente no puede listar condominios (R-10) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/condominiums` y `/towers`. Al completar el
DoD, se congela en `_state/contracts/CONTRACT_LOCKS.md` como `LOCK-PROPIEDADES-02`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (18 casos) — incluidos los negativos (3, 6, 7, 11, 13, 14, 16, 17, 18).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-PROPIEDADES-02` creada.
- [ ] `api/API_CONTRACT.md` actualizado con los nuevos endpoints y códigos de error.
- [ ] `api/endpoints/PROPIEDADES.md` actualizado con el detalle de request/response de los endpoints
      de condominios y torres.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> La regla de anti-enumeración (403 y 404 unificados, R-10) aplica a `show` de condominio ajeno y a
> `show` de torre de condominio ajeno. El middleware de tenant isolation debe estar activo desde AUTH.
