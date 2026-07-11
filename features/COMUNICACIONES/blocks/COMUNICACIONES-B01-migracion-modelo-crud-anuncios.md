---
tipo: bloque
proyecto: api
feature: COMUNICACIONES
id: COMUNICACIONES-B01
proyectos: [api]
estado: ready
depende_de: [AUTH-B01, PROPIEDADES-B03]
contrato: null
verificacion_critica: false
actualizado: 2026-07-11
---

# COMUNICACIONES-B01 — Migración, modelo y CRUD de anuncios

## Objetivo

Crear la tabla `announcements` (migración + modelo) y exponer sus 4 endpoints CRUD, con el permiso
nuevo `announcements.manage` (RBAC) para gestión y lectura abierta a cualquier usuario autenticado
vinculado al condominio. Al ser una feature chica de una sola tabla sin catálogos, este bloque cubre
toda la capa API — mismo criterio de alcance que `AUTH-B01` (fundación + CRUD en un solo bloque, sin
partir en migración-only + CRUD-only como sí ameritó `PROPIEDADES`/`DIRECTORIO` por tener catálogos
de sistema y múltiples tablas relacionadas).

## Alcance

- **Incluye:**
  - Migración `announcements`: `id` (UUID v7 PK), `condominium_id` (FK `→ condominiums.id`, NOT
    NULL, inmutable), `titulo` (text, NOT NULL), `cuerpo` (text, NOT NULL), `fijado` (bool, default
    `false`), `created_by`/`updated_by` (UUID nullable, FK `→ users.id`), `created_at`/`updated_at`/
    `deleted_at` (soft delete). Índice en `(condominium_id, fijado, created_at)` para el orden de
    lista (R-COM-04).
  - Modelo `EloquentAnnouncement` (traits `HasUuidV7`, `SoftDeletes`; relaciones `condominium()`,
    `createdBy()`, `updatedBy()` `belongsTo`).
  - Permiso nuevo `announcements.manage` en el catálogo de `permissions`, agregado a
    `RbacDemoSeeder.php` y adjunto a los roles `admin` y `manager` (mismo patrón que
    `admin_access`/`admin.access` en ese seeder).
  - Endpoints:
    - `GET /api/v1/condominiums/{condominium}/announcements` — lista, orden `fijado DESC, created_at
      DESC` (R-COM-04). Requiere solo autenticación + vínculo con el condominio (staff vía
      `role_assignment` o residente vía `property_occupants` — no requiere
      `announcements.manage`).
    - `POST /api/v1/condominiums/{condominium}/announcements` — crear. Requiere
      `announcements.manage`, scope `organization` o `condominium` (staff scoping, mismo criterio de
      R-09-bis).
    - `PATCH /api/v1/announcements/{announcement}` — editar (`titulo`, `cuerpo`, `fijado`). Requiere
      `announcements.manage`.
    - `DELETE /api/v1/announcements/{announcement}` — soft delete. Requiere `announcements.manage`.
  - Autorización: tenant isolation (R-COM-01, vía `condominium.organization_id`), staff scoping
    (R-COM-02), anti-enumeración 403/404 unificados (mismo patrón R-10 de `PROPIEDADES`) para
    condominios fuera del scope del usuario.
  - FormRequests de validación (`titulo`/`cuerpo` requeridos, `fijado` booleano opcional en create).
  - API Resource para la respuesta (envelope `{data: ...}` o `{announcement: ...}` — decidir
    siguiendo el mismo patrón ya usado por `PROPIEDADES`/`DIRECTORIO`, documentarlo en
    `api/endpoints/COMUNICACIONES.md`).

- **No incluye (explícitamente fuera de este bloque):**
  - Segmentación de destinatarios, canales externos, plantillas, encuestas, estado
    borrador/programado/enviado (fuera de esta feature, ver PANORAMA §4).
  - Cualquier UI — eso es `COMUNICACIONES-B02`.
  - Endpoint consumido por `PORTAL_RESIDENTE` distinto al de lectura ya expuesto aquí — ese feature
    (no diseñado todavía) reusa este mismo `GET`, no crea uno propio.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `announcements.manage` en el condominio | `POST /condominiums/{id}/announcements` con `titulo`+`cuerpo` | `201`, anuncio creado con `created_by` = usuario actual, `fijado = false` por defecto |
| 2 | Usuario **sin** `announcements.manage` (ej. rol `resident`) | `POST /condominiums/{id}/announcements` | `403` — sin permiso de gestión |
| 3 | Usuario autenticado sin vínculo alguno con el condominio (ni staff scope ni `property_occupants`) | `GET /condominiums/{id}/announcements` | `403`/`404` unificado (anti-enumeración, R-10) |
| 4 | Usuario residente (vía `property_occupants`, sin `role_assignment` de gestión) del condominio | `GET /condominiums/{id}/announcements` | `200` — ve la lista completa, aunque no tenga `announcements.manage` (R-COM-02) |
| 5 | 3 anuncios existentes: 2 sin fijar (creados en momentos distintos), 1 fijado creado primero que los otros dos | `GET /condominiums/{id}/announcements` | El fijado aparece primero, los otros dos en orden `created_at DESC` (R-COM-04) |
| 6 | Anuncio existente, usuario con `announcements.manage` | `PATCH /announcements/{id}` con `{"fijado": true}` | `200` — `fijado` actualizado, `updated_by` = usuario actual |
| 7 | Anuncio de un condominio fuera del scope del usuario (`role_assignment.scope_type = condominium` en otro condominio) | `PATCH /announcements/{id}` | `403`/`404` unificado |
| 8 | Anuncio existente, usuario con `announcements.manage` | `DELETE /announcements/{id}` | `200`/`204` — `deleted_at` poblado (soft delete), ya no aparece en `GET` de lista |
| 9 | `condominium_id` inexistente | `GET /condominiums/{id}/announcements` | `404` |
| 10 | `titulo` o `cuerpo` vacío | `POST /condominiums/{id}/announcements` | `422` — error de validación |
| 11 | Dos condominios de organizaciones distintas, cada uno con sus anuncios | Usuario de la organización A hace `GET` sobre el condominio de la organización B | `403`/`404` unificado (R-COM-01, tenant isolation) |

> Cubre camino feliz (1, 5, 6), autorización negativa (2, 3, 7, 11) y validación (9, 10) — al menos
> un caso negativo/de seguridad por cada acción de escritura, según exige el DoD.

## Contrato

Este bloque **produce** el contrato — se congela como `LOCK-COMUNICACIONES-01` en
`_state/contracts/CONTRACT_LOCKS.md` al llegar a `done`, cubriendo los 4 endpoints de arriba.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Migración con `down()` reversible confirmado (`migrate` → `migrate:rollback` → `migrate` sin
      error) — salida pegada.
- [ ] Verificación funcional real (request/response reales) cubriendo los 11 criterios de
      aceptación, incluidos los casos negativos (2, 3, 7, 9, 10, 11) — no solo el camino feliz.
- [ ] Entrada `LOCK-COMUNICACIONES-01` creada en `_state/contracts/CONTRACT_LOCKS.md` con los 4
      endpoints, request/response, errores documentados y reglas de negocio aplicadas.
- [ ] `api/API_DATABASE.md` actualizado: tabla `announcements` documentada con esquema real.
- [ ] `api/endpoints/COMUNICACIONES.md` creado con el detalle de request/response de los 4
      endpoints (formato de envelope, errores, ejemplos reales).
- [ ] `RbacDemoSeeder.php` actualizado (`announcements.manage` creado y adjunto a `admin`/`manager`)
      — confirmar que no rompe los tests de RBAC existentes.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> `condominium_id` es inmutable (mismo R-07 de `PROPIEDADES`) — no hay endpoint para mover un
> anuncio entre condominios; si se necesitara, sería borrar y crear uno nuevo.
