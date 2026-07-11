---
tipo: bloque
proyecto: api
feature: PORTERIA
id: PORTERIA-B01
proyectos: [api]
estado: ready
depende_de: [AUTH-B05, PROPIEDADES-B03, PROPIEDADES-B04]
contrato: null
verificacion_critica: false
actualizado: 2026-07-11
---

# PORTERIA-B01 — Migraciones, modelos, rol `vigilante` y CRUD de visitas/paquetes

## Objetivo

Crear las tablas `visits` y `packages` (migración + modelo), el rol `vigilante` y el permiso
`porteria.manage` (RBAC), y exponer los endpoints de ambas entidades. Feature chica (dos tablas
relacionadas, sin catálogos) — un solo bloque cubre toda la capa API, mismo criterio de alcance que
`COMUNICACIONES-B01`.

## Alcance

- **Incluye:**
  - Migración `visits`: `id` (UUID v7 PK), `condominium_id` (FK `→ condominiums.id`, NOT NULL,
    inmutable), `property_id` (FK `→ properties.id`, NOT NULL, inmutable), `visitante_nombre`
    (text, NOT NULL), `visitante_documento` (text, NOT NULL), `vehiculo_placa` (text, nullable),
    `ingreso_at` (timestamptz, NOT NULL, default al crear), `salida_at` (timestamptz, nullable),
    `registrado_por` (FK `→ users.id`, NOT NULL), `cerrado_por` (FK `→ users.id`, nullable),
    `created_at`/`updated_at`/`deleted_at`. Índice en `(condominium_id, salida_at)` para el filtro
    de "visitas activas".
  - Migración `packages`: `id` (UUID v7 PK), `condominium_id` (FK, NOT NULL, inmutable),
    `property_id` (FK, NOT NULL, inmutable), `transportadora` (text, nullable), `descripcion`
    (text, NOT NULL), `recibido_at` (timestamptz, NOT NULL, default al crear), `recibido_por` (FK
    `→ users.id`, NOT NULL), `entregado_at` (timestamptz, nullable), `entregado_por` (FK
    `→ users.id`, nullable), `entregado_a` (FK `→ contacts.id`, nullable), `created_at`/
    `updated_at`/`deleted_at`. Índice en `(condominium_id, entregado_at)` para el filtro de
    "pendientes".
  - Modelos `EloquentVisit`, `EloquentPackage` (traits `HasUuidV7`, `SoftDeletes`; relaciones
    `condominium()`, `property()`, `registradoPor()`/`cerradoPor()` o `recibidoPor()`/
    `entregadoPor()`/`entregadoA()`, todas `belongsTo`).
  - Permiso nuevo `porteria.manage` en el catálogo de `permissions`.
  - Rol nuevo `vigilante` en `RbacDemoSeeder.php` (no existía, ver PANORAMA §1/R-PORT-05) — adjuntar
    `porteria.manage`. Adjuntar el mismo permiso a `admin` y `manager` (ya existentes en el
    seeder).
  - Endpoints:
    - `GET /api/v1/condominiums/{condominium}/visits` (`?property_id=&estado=activa|cerrada`) —
      `estado` es un filtro derivado de `salida_at`, no una columna (R-PORT-04).
    - `POST /api/v1/condominiums/{condominium}/visits` — registrar ingreso. `ingreso_at` = ahora,
      `registrado_por` = usuario actual.
    - `PATCH /api/v1/visits/{visit}` — corregir `visitante_nombre`/`visitante_documento`/
      `vehiculo_placa` de una visita **activa** (no editable tras cerrada).
    - `PATCH /api/v1/visits/{visit}/salida` — registrar salida. `salida_at` = ahora, `cerrado_por` =
      usuario actual. Rechaza si ya tiene `salida_at` (409).
    - `GET /api/v1/condominiums/{condominium}/packages` (`?property_id=&estado=pendiente|entregado`)
      — mismo criterio de filtro derivado.
    - `POST /api/v1/condominiums/{condominium}/packages` — registrar recepción. `recibido_at` =
      ahora, `recibido_por` = usuario actual.
    - `PATCH /api/v1/packages/{package}` — corregir `transportadora`/`descripcion` de un paquete
      **pendiente** (no editable tras entregado).
    - `PATCH /api/v1/packages/{package}/entrega` — registrar entrega. Requiere `entregado_a`
      (`contact_id`) en el body; valida que sea ocupante activo (`property_occupants`) de la unidad
      del paquete (R-PORT-09) → `422` si no lo es. `entregado_at` = ahora, `entregado_por` = usuario
      actual. Rechaza si ya tiene `entregado_at` (409).
  - Autorización: tenant isolation (R-PORT-01), staff scoping (R-PORT-02), permiso único
    `porteria.manage` para toda acción de este bloque (lectura y escritura — R-PORT-03), sin acceso
    residente.
  - FormRequests de validación correspondientes.
  - API Resources para las respuestas.

- **No incluye (explícitamente fuera de este bloque):**
  - Cualquier UI — eso es `PORTERIA-B02`/`PORTERIA-B03`.
  - `DELETE` de cualquiera de las dos entidades — no existe, ver R-PORT-07.
  - Foto de evidencia de paquete, QR/PIN, rondas, botón de pánico, parqueo de visitantes, listas de
    acceso, turnos de vigilancia (fuera de esta feature, ver PANORAMA §3/§4).
  - Validación de que `entregado_a` pertenezca a `DIRECTORIO` más allá de la consulta a
    `property_occupants` ya existente — no crea endpoints nuevos de `DIRECTORIO`.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `porteria.manage` (vigilante) en el condominio | `POST /condominiums/{id}/visits` con `property_id`+`visitante_nombre`+`visitante_documento` | `201`, `ingreso_at` poblado, `registrado_por` = usuario actual, `salida_at` NULL |
| 2 | Usuario **sin** `porteria.manage` (ej. rol `resident`) | `POST /condominiums/{id}/visits` | `403` |
| 3 | Visita activa (`salida_at` NULL) | `PATCH /visits/{id}/salida` | `200`, `salida_at` poblado, `cerrado_por` = usuario actual |
| 4 | Visita ya cerrada (`salida_at` no NULL) | `PATCH /visits/{id}/salida` de nuevo | `409` — ya tiene salida registrada |
| 5 | 2 visitas activas + 1 cerrada en el mismo condominio | `GET /condominiums/{id}/visits?estado=activa` | Devuelve solo las 2 activas |
| 6 | Usuario con `porteria.manage`, condominio A | `POST /condominiums/{idB}/packages` sobre un condominio B fuera de su scope | `403`/`404` unificado (anti-enumeración, R-10) |
| 7 | Paquete recién creado (`entregado_at` NULL) | `PATCH /packages/{id}/entrega` con `entregado_a` = contacto que **no** es ocupante activo de esa unidad | `422` — destinatario inválido (R-PORT-09) |
| 8 | Paquete recién creado, `entregado_a` = ocupante activo real de la unidad | `PATCH /packages/{id}/entrega` | `200`, `entregado_at`/`entregado_por` poblados |
| 9 | Paquete ya entregado | `PATCH /packages/{id}/entrega` de nuevo | `409` — ya fue entregado |
| 10 | Paquete pendiente | `PATCH /packages/{id}` con `descripcion` corregida | `200` — actualizado |
| 11 | Paquete ya entregado | `PATCH /packages/{id}` intentando corregir `descripcion` | `409`/`422` — no editable tras entrega |
| 12 | `property_id` inexistente | `POST /condominiums/{id}/visits` | `404` |
| 13 | `visitante_nombre` o `visitante_documento` vacío | `POST /condominiums/{id}/visits` | `422` |
| 14 | Rol `vigilante` recién seedeado | Verificar catálogo de `roles`/`permissions` | `vigilante` existe con `porteria.manage` adjunto; `admin`/`manager` también lo tienen |

> Cubre camino feliz (1, 3, 8, 10), autorización negativa (2, 6), transición de estado inválida (4,
> 9, 11), validación de negocio (7) y validación básica (12, 13) — al menos un caso negativo por
> cada acción de escritura.

## Contrato

Este bloque **produce** el contrato — se congela como `LOCK-PORTERIA-01` en
`_state/contracts/CONTRACT_LOCKS.md` al llegar a `done`, cubriendo los 8 endpoints de arriba.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Migraciones con `down()` reversible confirmado para ambas tablas — salida pegada.
- [ ] Verificación funcional real cubriendo los 14 criterios de aceptación, incluidos los negativos
      (2, 4, 6, 7, 9, 11, 12, 13).
- [ ] Entrada `LOCK-PORTERIA-01` creada en `_state/contracts/CONTRACT_LOCKS.md`.
- [ ] `api/API_DATABASE.md` actualizado: `visits` y `packages` documentadas con esquema real.
- [ ] `api/endpoints/PORTERIA.md` creado con el detalle de los 8 endpoints.
- [ ] `RbacDemoSeeder.php` actualizado (`vigilante` + `porteria.manage` creados y adjuntos) —
      confirmar que no rompe tests de RBAC existentes.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> `condominium_id`/`property_id` son inmutables (R-PORT-08) — no hay endpoint para mover una
> visita/paquete a otra unidad. Si el vigilante se equivoca de unidad al registrar, la corrección es
> vía soporte/BD directa en esta versión — no se expone un endpoint de "reasignar unidad" por ser un
> caso excepcional, no la operación normal.
