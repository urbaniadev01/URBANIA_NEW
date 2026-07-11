---
tipo: feature
proyecto: shared
feature: COMUNICACIONES
estado_diseño: approved
actualizado: 2026-07-11
---

# Feature: COMUNICACIONES

## 1. Resumen y motivación

COMUNICACIONES (básico) permite a un administrador/manager publicar anuncios simples para un
condominio — el reemplazo digital de la cartelera física. Es la feature 1.4 del MVP (Fase 1),
independiente de `COBRANZA`/`DIRECTORIO` (solo depende de `AUTH`, ya `SHIPPED`), y a su vez es
prerrequisito de `PORTAL_RESIDENTE` (1.6), que embeberá el listado de anuncios como uno de sus
widgets ("mis avisos").

Alcance deliberadamente acotado — la versión completa (segmentación por torre/morosos/unidad,
canales WhatsApp/email/push, plantillas, encuestas) es la feature 2.9 de Fase 2, no esta. Esta
versión resuelve un solo caso: "el admin publica algo, cualquier persona del condominio lo lee".

## 2. Capas afectadas

- [x] API (origen del contrato)
- [x] Web
- [ ] App — diferido, ver [[../../app/APP_DEFERRED]]

## 3. Relación con otras features

- Depende de: [[../AUTH/PANORAMA]] — identidad vía JWT, tenant vía `organization_id`, RBAC vía
  `role_assignments`. Depende de [[../PROPIEDADES/PANORAMA]] — necesita `condominiums.id` para
  vincular cada anuncio a un condominio (`PROPIEDADES-B03`, ya `done`).
- Es consumido por: `PORTAL_RESIDENTE` (no diseñada todavía, feature 1.6) — usará el endpoint de
  lectura de anuncios como widget "mis avisos" del agregador; no repite el endpoint, lo embebe.
- **Explícitamente fuera de esta feature:** segmentación de destinatarios, canales externos
  (WhatsApp/email/push), plantillas, encuestas, métricas de entrega/lectura — todo eso es
  `COMUNICACIONES` (completo), feature 2.9 de Fase 2.

## 4. Modelo de datos

Convenciones de columnas: [[../../shared/DATA_MODEL]] §1. Auditoría (`created_by`/`updated_by`):
§1-bis (vigente desde `PROPIEDADES`).

| Entidad | Nueva/Existente | Campo | Valor/Referencia | Notas |
|---|---|---|---|---|
| `announcements` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL, inmutable (mismo patrón R-07 de `PROPIEDADES`) |
| | | `titulo` | Valor (text) | NOT NULL |
| | | `cuerpo` | Valor (text) | NOT NULL. Sin formato enriquecido (texto plano) — un editor rich-text es mejora de Fase 2, no de este bloque |
| | | `fijado` | Valor (bool) | Default `false`. Sube el anuncio al tope de la lista (cartelera) |
| | | `created_by` / `updated_by` | Referencia (`→ users.id`, nullable) | R-11 heredada de `PROPIEDADES` |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar |

**Explícitamente fuera de esta tabla (vs. el research completo):** `autor_user_id` (se cubre con
`created_by`, no hace falta un campo separado), `segmento`/`target_id` (siempre visible a todo el
condominio), `estado` (borrador/programado/enviado — se publica al crear, sin ciclo de estados),
`programado_para` (sin programación futura). Estos cuatro son la razón de ser de la versión completa
(Fase 2, 2.9) — no se construyen especulativamente ahora.

## 5. Reglas de negocio globales

- **R-COM-01 — Tenant isolation:** toda query de `announcements` scopea transitivamente por
  `condominium_id → condominiums.organization_id` (mismo criterio de RLS del resto del sistema,
  ADR-001 §1).
- **R-COM-02 — Gestión con permiso, lectura abierta:** crear/editar/eliminar un anuncio requiere el
  permiso `announcements.manage` (nuevo, catálogo de `permissions`), con el mismo patrón de
  aislamiento por scope de staff que R-09-bis de `PROPIEDADES`/R-DIR-03 de `DIRECTORIO`
  (`role_assignment.scope_type ∈ {condominium, tower}` limita a su(s) condominio(s)). Leer el
  listado **no** requiere ese permiso — cualquier usuario autenticado con un vínculo al condominio
  (staff vía `role_assignment`, residente vía `property_occupants`) puede verlo.
- **R-COM-03 — Publicación inmediata:** no existe estado borrador/programado en esta versión — un
  anuncio creado es visible de inmediato. Ver nota de alcance en §4.
- **R-COM-04 — Orden de lista:** `fijado` primero (`fijado DESC, created_at DESC`) — no hay límite
  de cuántos anuncios pueden estar fijados a la vez.
- **R-COM-05 — Auditoría de autoría:** `created_by`/`updated_by` (R-11 heredada de `PROPIEDADES`,
  `shared/DATA_MODEL.md` §1-bis).
- **R-COM-06 — Borrado suave:** eliminar un anuncio es soft-delete (`deleted_at`), nunca físico —
  estándar de `shared/DATA_MODEL.md` §1.

## 6. Mapeo de acciones a endpoints (alto nivel)

El detalle de request/response vive en `api/endpoints/COMUNICACIONES.md` — aquí solo el mapeo.

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar anuncios de un condominio (staff o residente) | GET | `/condominiums/{id}/announcements` |
| Crear anuncio | POST | `/condominiums/{id}/announcements` |
| Editar anuncio (incluye alternar `fijado`) | PATCH | `/announcements/{id}` |
| Eliminar anuncio | DELETE | `/announcements/{id}` |

## 7. UI/UX

**Tier:** Estándar — lista + formulario CRUD, sin visualización de datos ni layout no convencional.

### 7.1 Pantallas afectadas

| Pantalla | Tipo | Ruta | Nueva/Existente |
|---|---|---|---|
| Anuncios | Página | `/comunicaciones/anuncios` | Nueva |
| Crear/editar anuncio | Sheet | (dentro de la página anterior) | Nueva |

### 7.2 Componentes de librería usados

`Card` (un anuncio = una card, no una tabla — el volumen esperado en un solo condominio no justifica
una `DataTable`; la convención única de `DataTable` sigue pendiente de decidirse en la primera
pantalla que sí la necesite, ver `web/WEB_VISUAL_STANDARDS.md` §7.3 del plan de fases), `Sheet`
(crear/editar), `Badge` (marcar "Fijado"), `Button`, `Textarea`, `Input`, `AlertDialog` (confirmar
eliminación — acción destructiva).

### 7.3 Estados de la vista

| Pantalla | Loading | Vacío | Error | Éxito |
|---|---|---|---|---|
| Anuncios | Skeleton de cards | "Todavía no hay anuncios" + CTA "Publicar el primero" (solo visible con permiso `announcements.manage`) | Toast + estado de reintento | Toast de confirmación al crear/editar/eliminar |

### 7.4 Navegación

Nueva entrada de sidebar "Comunicaciones" → "Anuncios", visible a cualquier usuario autenticado
(lectura); las acciones de crear/editar/eliminar se gatean en la UI por el permiso
`announcements.manage` del usuario (mismo patrón que el grupo "Administración" ya usa con
`admin.access`).

## 8. Plan de bloques

Una vez `estado_diseño: approved`, el detalle de bloques vive en `BLOCKS.md` (mismo directorio que
este panorama).

## 9. Checklist de aprobación (gate)

- [x] §4 (modelo de datos): cada campo nuevo declara Valor o Referencia explícitamente
- [x] §6 (mapeo de acciones a endpoints) cubre toda acción visible al usuario descrita en §1/§5
- [x] §7 (UI/UX) completa: pantallas, componentes y estados declarados (Tier Estándar, sin
      wireframe/responsive obligatorios)
- [x] Nombres de campos y entidades consistentes con `shared/GLOSSARY.md` — términos nuevos
      ("Anuncio", `fijado`) agregados en esta misma sesión (ver `shared/GLOSSARY.md` §"Términos de
      comunicaciones")
- [x] No hay una feature existente en `features/` que ya cubra esto (revisada `_state/BOARD.md`) —
      confirmado, solo `AUTH`/`API_BOOTSTRAP`/`WEB_BOOTSTRAP`/`PROPIEDADES`/`DIRECTORIO`/`DASHBOARD`/
      `COBRANZA` existen

> Aprobado por el usuario vía revisión directa del panorama en la misma conversación en que se
> redactó este documento (2026-07-11) — el gate humano de `_system/03_LIFECYCLE.md` §3.

## 10. Análisis de diseño (Claude Code, no un Design Council multi-agente)

Panorama redactado por Claude Code en modo asesoría directa (mismo criterio que
[[../DIRECTORIO/PANORAMA]] §9) — feature clasificada como "Simple" (pocos endpoints, una pantalla,
sin reglas intrincadas), por lo que no se corrió el protocolo de 3 fases (divergencia → peer review →
síntesis) reservado para features complejas. Alcance acotado usando
`D:\Programacion\URBANIA_DEV_PLAN\PLAN_FASES_DESARROLLO.md` (ítem 1.4) como guía no literal, y el
research de pantallas/modelo de datos externo como referencia de la versión completa a recortar, no
a copiar. Tres decisiones de alcance se confirmaron con el usuario antes de redactar: lectura de
solo-lectura ya en esta feature (no diferida a `PORTAL_RESIDENTE`), concepto `fijado` incluido desde
ya, y permiso de gestión abierto a `admin` y `manager` (no solo `admin`).
