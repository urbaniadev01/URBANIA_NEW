---
tipo: feature
proyecto: shared
feature: DIRECTORIO
estado_diseño: approved
actualizado: 2026-07-08
---

# Feature: DIRECTORIO

## 1. Resumen y motivación

DIRECTORIO gestiona el directorio de personas de un condominio: contactos (con o sin cuenta de
login), el catálogo de tipos de ocupante, y la asignación persona↔unidad
(`property_occupants`). Es la feature 1.2 del MVP (Fase 1) — el prerrequisito transversal de
`COBRANZA` (a quién se factura/quién paga), `PORTERIA` (a quién se entrega un paquete, quién autoriza
una visita) y, más adelante, `PQRS_CUMPLIMIENTO` (quién radica una queja). Sin esto, ninguna de esas
tres puede vincular una acción a una persona real de la unidad.

Esta feature también **corrige una brecha entre el diseño aprobado de AUTH y su implementación**: ver
§4, nota sobre `contacts`.

## 2. Capas afectadas

- [x] API (origen del contrato)
- [x] Web
- [ ] App — diferido, ver [[../../app/APP_DEFERRED]]

## 3. Relación con otras features

- Depende de: [[../AUTH/PANORAMA]] — `contacts`/`users` ya existen (con una corrección, ver §4);
  identidad vía JWT, tenant vía `organization_id`, RBAC vía `role_assignments`. Depende de
  [[../PROPIEDADES/PANORAMA]] — necesita `properties.id` para vincular persona↔unidad
  (`PROPIEDADES-B01`, ya `done`; no depende de que B02-B05 de esa feature estén terminados).
- Es consumido por:
  - `COBRANZA` (no diseñada todavía) — usará `contact_id` para registrar quién paga
    (`payment_receipts.contact_id`).
  - `PORTERIA` (no diseñada todavía) — usará `contact_id` para entrega de paquetes y autorización de
    visitas.
  - [[../PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]] — su regla "no eliminar unidad con
    ocupantes activos" (R-03 de PROPIEDADES) tiene hoy un guard clause temporal que asume "sin
    ocupantes"; el bloque fundacional de esta feature (`DIRECTORIO-B01`) debe reemplazarlo por la
    verificación real (ver nota en `_state/BOARD.md` y en la propia tarjeta de `PROPIEDADES-B04`).
- **Explícitamente fuera de esta feature:** facturación (`COBRANZA`), visitas/paquetes (`PORTERIA`),
  historial temporal de ocupación (ver punto ciego en §5, R-DIR-05).

## 4. Modelo de datos

### Corrección previa: `contacts` no cumple su propio diseño aprobado

`features/AUTH/PANORAMA.md` §4 documenta `contacts.user_id` como **"Referencia, nullable"**, citando
el invariante de [[../../shared/adr/ADR-001-actor-party]] §3: *"todo `user` activo tiene un `contact`
asociado... Un `contact` puede existir sin `user`"* (propietario ausente, registrado por obligación
de la Ley 675 de 2001). La migración real de `AUTH-B01` (ya `done`/`SHIPPED`) implementó `user_id`
como `unique()` sin `nullable()`, con `cascadeOnDelete` — hoy es imposible crear un contacto sin
usuario. `AUTH-B01` no se reabre (ya está `done`); esta feature es la primera que de verdad necesita
el invariante, así que su bloque fundacional (`DIRECTORIO-B01`) corrige la tabla existente con una
migración correctiva explícita (no silenciosa — ver DoD de esa tarjeta).

Consecuencia relacionada: `contacts` no tiene `organization_id` propio hoy — el tenant se deriva
transitivamente vía `user_id → users.organization_id`. Si `user_id` puede ser `NULL`, un contacto sin
usuario queda sin organización, rompiendo el aislamiento multi-tenant (R-DIR-01). Se agrega
`organization_id` directo, con backfill desde `users.organization_id` para las filas existentes.

Al volverse `organization_id` `NOT NULL`, el flujo de registro por invitación de `AUTH-B01`
(`RegisterUserUseCase`) deja de funcionar si no se actualiza — hoy crea el `Contact` sin
`organization_id`. `DIRECTORIO-B01` incluye ese parche de una línea como parte de la misma corrección
(ver esa tarjeta), con un test de regresión explícito para no romper el registro ya `SHIPPED`.

### Tablas tocadas/nuevas (1 corrección + 2 nuevas)

Convenciones de columnas: [[../../shared/DATA_MODEL]] §1. Auditoría (`created_by`/`updated_by`):
§1-bis (vigente desde `PROPIEDADES`, se mantiene aquí).

| Entidad | Nueva/Existente | Campo | Valor/Referencia | Notas |
|---|---|---|---|---|
| `contacts` | **Existente, corregida** | `organization_id` | Referencia (`→ organizations.id`) | **Columna nueva.** NOT NULL tras backfill. Tenant directo, ya no transitivo vía `user_id`. |
| | | `user_id` | Referencia (`→ users.id`, nullable) | **Cambia de NOT NULL a nullable.** Índice único pasa a parcial: `UNIQUE(user_id) WHERE user_id IS NOT NULL`. |
| | | `created_by` | Referencia (`→ users.id`, nullable) | Columna nueva (R-11 heredada de PROPIEDADES). `NULL` para contactos creados junto con el registro por invitación (AUTH), donde no hay todavía un actor administrativo distinto del propio usuario. |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | Columna nueva. |
| `occupant_types` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `organization_id` | Referencia (`→ organizations.id`, nullable) | NULL = catálogo de sistema, NOT NULL = personalizado (mismo patrón que `property_types`) |
| | | `nombre` | Valor (text) | NOT NULL |
| | | `descripcion` | Valor (text, nullable) | |
| | | `created_by` / `updated_by` | Referencia (`→ users.id`, nullable) | `NULL` en catálogo de sistema (seed) |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar |
| `property_occupants` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `contact_id` | Referencia (`→ contacts.id`) | NOT NULL |
| | | `property_id` | Referencia (`→ properties.id`) | NOT NULL |
| | | `occupant_type_id` | Referencia (`→ occupant_types.id`) | NOT NULL |
| | | `es_principal` | Valor (bool) | Default `false`. Distingue a quién se factura/notifica por defecto cuando una unidad tiene varios ocupantes del mismo tipo (ver R-DIR-06). |
| | | `created_by` / `updated_by` | Referencia (`→ users.id`, nullable) | R-11 |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar. Sin temporalidad de vigencia (ver R-DIR-05, punto ciego explícito). |

> **Nota sobre `es_principal`:** no reemplaza una relación de propiedad legal (eso lo determinaría
> `COBRANZA`/notaría vía `peace_certificates`, fuera de esta feature) — es solo un flag operativo
> para saber a quién dirigir por defecto una factura o notificación cuando hay más de un ocupante del
> mismo `occupant_type` en la misma unidad. Único por `(property_id, occupant_type_id)` mientras
> `es_principal = true` — ver R-DIR-07.

## 5. Reglas de negocio globales

- **R-DIR-01 — Tenant isolation directo:** toda query de `contacts`/`property_occupants` scopea por
  `contacts.organization_id` (ya no transitivo vía `user_id`). Mismo criterio de RLS que el resto del
  sistema (ADR-001 §1).
- **R-DIR-02 — Contacto con o sin login:** un `contact` puede existir sin `user_id` (propietario
  ausente, familiar sin cuenta) o con `user_id` (residente/staff con cuenta). La creación de
  contactos sin login es la razón de ser de esta feature — no es un caso borde.
- **R-DIR-03 — Aislamiento por scope de staff:** mismo patrón que R-09-bis de `PROPIEDADES` — un
  usuario con `role_assignment.scope_type ∈ {condominium, tower}` solo gestiona contactos/ocupantes
  dentro de su(s) scope(s) asignado(s). Anti-enumeración 403/404 unificados.
- **R-DIR-04 — Autogestión del propio contacto:** un usuario autenticado siempre puede leer/editar su
  propio `contact` (vía `contacts.user_id = auth user`) a través de `/me/contact`, sin necesitar un
  `role_assignment` de gestión — no ve el contacto de otros residentes fuera de su scope de gestión.
- **R-DIR-05 — Sin temporalidad de ocupación (punto ciego explícito, no resuelto por invención):** a
  diferencia de `property_coefficients` (que sí necesita vigencia histórica para `COBRANZA`), no hay
  evidencia en el research de que el MVP necesite historial de "quién ocupó qué unidad y cuándo". Se
  documenta como deuda técnica explícita: si `COBRANZA`/legal necesitan más adelante probar quién era
  el ocupante en una fecha pasada, se agrega `vigente_desde`/`vigente_hasta` en un bloque nuevo — no
  se construye especulativamente ahora.
- **R-DIR-06 — Habeas Data (Ley 1581 de 2012):** `email`/`telefono` de un contacto no se exponen a
  otro residente — solo a staff con permiso de gestión de contactos. Un residente que consulta el
  directorio de su propia unidad ve nombres pero no datos de contacto de otros ocupantes salvo que
  tenga un `role_assignment` administrativo.
- **R-DIR-07 — Un solo `es_principal` por tipo y unidad:** `UNIQUE(property_id, occupant_type_id)
  WHERE es_principal = true AND deleted_at IS NULL`. Marcar uno nuevo como principal desmarca
  automáticamente al anterior (mismo espíritu que R-05 de coeficientes: "crear uno nuevo cierra al
  anterior", aplicado aquí a un booleano en vez de una fecha).
- **R-DIR-08 — No eliminar contacto con ocupaciones activas:** no se puede soft-delete un `contact`
  que tenga `property_occupants` activos (sin `deleted_at`) → `409 CONTACT_HAS_OCCUPATIONS`. Un
  contacto vinculado a un `user` se elimina en cascada solo cuando el `user` se elimina (ya
  implementado en AUTH vía `cascadeOnDelete`) — esta regla aplica al `DELETE /contacts/{id}` directo.
- **R-DIR-09 — Catálogo de sistema inmutable:** `occupant_types` con `organization_id = NULL` no
  puede ser editado ni eliminado por tenants (mismo R-08 de `PROPIEDADES`).
- **R-DIR-10 — Auditoría de autoría:** `created_by`/`updated_by` en las 3 tablas tocadas/nuevas
  (R-11 heredada de `PROPIEDADES`, `shared/DATA_MODEL.md` §1-bis).
- **R-DIR-11 — Unicidad de asignación:** `UNIQUE(contact_id, property_id, occupant_type_id) WHERE
  deleted_at IS NULL` — un mismo contacto puede tener varios tipos en la misma unidad (ej. dueño y
  residente a la vez), pero no el mismo tipo duplicado.

## 6. Mapeo de acciones a endpoints (alto nivel)

El detalle de request/response vive en `api/endpoints/DIRECTORIO.md` — aquí solo el mapeo.

### Catálogo de tipos de ocupante

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar tipos de ocupante | GET | `/occupant-types` |
| Crear tipo de ocupante | POST | `/occupant-types` |
| Editar tipo de ocupante | PATCH | `/occupant-types/{id}` |
| Eliminar tipo de ocupante | DELETE | `/occupant-types/{id}` |

### Contactos

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar contactos de la organización | GET | `/contacts` (?search=) |
| Ver contacto | GET | `/contacts/{id}` |
| Crear contacto (con o sin `user_id`) | POST | `/contacts` |
| Editar contacto | PATCH | `/contacts/{id}` |
| Eliminar contacto | DELETE | `/contacts/{id}` |
| Ver mi propio contacto | GET | `/me/contact` |
| Editar mi propio contacto | PATCH | `/me/contact` |
| Unidades asociadas a un contacto | GET | `/contacts/{id}/properties` |

### Asignación persona↔unidad

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar ocupantes de una unidad | GET | `/properties/{id}/occupants` |
| Asignar un contacto a una unidad | POST | `/properties/{id}/occupants` |
| Editar asignación (tipo / `es_principal`) | PATCH | `/property-occupants/{id}` |
| Des-asignar (quitar ocupante) | DELETE | `/property-occupants/{id}` |

## 7. Plan de bloques

Una vez `estado_diseño: approved`, el detalle de bloques vive en `BLOCKS.md` (mismo directorio que
este panorama).

## 8. Checklist de aprobación (gate)

- [x] §4: cada campo nuevo declara Valor o Referencia
- [x] §6 cubre toda acción visible al usuario descrita en §1/§5
- [x] Nombres de campos y entidades consistentes con [[../../shared/GLOSSARY]] (Ocupante, Tipo de
      ocupante ya estaban pre-declarados ahí desde el diseño de `PROPIEDADES`)
- [x] No hay una feature existente en `features/` que ya cubra esto (revisar `_state/BOARD.md`) —
      confirmado, solo `AUTH`/`API_BOOTSTRAP`/`WEB_BOOTSTRAP`/`PROPIEDADES` existen
- [x] Nuevos términos de dominio agregados a [[../../shared/GLOSSARY]] — "Contacto sin login" y
      "`es_principal`" agregados 2026-07-08

> Aprobado por el usuario vía revisión del plan de diseño en la misma conversación en que se redactó
> este documento (2026-07-08) — el gate humano de `_system/03_LIFECYCLE.md` §3.

## 9. Análisis de diseño (Claude Code, no un Design Council multi-agente)

A diferencia de `PROPIEDADES/PANORAMA.md` (producido por el protocolo LLM Council de 3 fases de
`doc-agent`), este panorama lo redactó Claude Code directamente en modo asesoría, a pedido explícito
del usuario, usando `D:\Programacion\URBANIA_DEV_PLAN\` como guía no literal y contrastando sus
afirmaciones contra el código real ya implementado. No hay una sección de "veredicto de council"
porque no hubo ese proceso — el hallazgo central (la corrección de `contacts`) surgió de comparar el
plan externo, el diseño aprobado de AUTH, y la migración real, no de una síntesis de posturas
divergentes.
