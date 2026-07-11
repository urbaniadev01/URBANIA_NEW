---
tipo: bloque
proyecto: web
feature: COMUNICACIONES
id: COMUNICACIONES-B02
proyectos: [web]
estado: backlog
depende_de: [COMUNICACIONES-B01, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: true
actualizado: 2026-07-11
---

# COMUNICACIONES-B02 — Pantalla de anuncios

## Objetivo

Construir la única pantalla de esta feature: "Anuncios" (`/comunicaciones/anuncios`), lista de
comunicados con crear/editar/eliminar, integrada con `LOCK-COMUNICACIONES-01`. Es el último bloque
de la feature — cierra `COMUNICACIONES` completa (ver `verificacion_critica` abajo).

## Alcance

- **Incluye:**
  - Página `Anuncios` (`/comunicaciones/anuncios`): lista de cards (no `DataTable` — ver PANORAMA
    §7.2), orden `fijado` primero, cada card muestra título, cuerpo, badge "Fijado" si aplica,
    fecha, y — solo si el usuario tiene `announcements.manage` — botones editar/eliminar.
  - Sheet de crear/editar: campos `titulo` (input), `cuerpo` (textarea), `fijado` (switch/checkbox).
    Validación Zod (`titulo`/`cuerpo` requeridos).
  - `AlertDialog` de confirmación antes de eliminar (acción destructiva, mismo patrón ya establecido
    en `web/WEB_VISUAL_STANDARDS.md` §6).
  - Botón "Publicar anuncio" / CTA de creación, visible solo con `announcements.manage`.
  - Estados: loading (skeleton de cards), vacío ("Todavía no hay anuncios" + CTA condicionado al
    permiso), error (toast + reintento), éxito (toast de confirmación en crear/editar/eliminar) —
    según PANORAMA §7.3.
  - Integración API: hooks/clients para `LOCK-COMUNICACIONES-01` (`GET`/`POST`
    `/condominiums/{id}/announcements`, `PATCH`/`DELETE` `/announcements/{id}`).
  - Entrada de sidebar nueva "Comunicaciones" → "Anuncios", vía `registerSidebarItem()` (patrón
    Widget Registry, mismo mecanismo que `features/propiedades/dashboard.ts`), visible a todo
    usuario autenticado (lectura abierta); las acciones de gestión se gatean en la UI por
    `announcements.manage`, mismo criterio que el grupo "Administración" existente con
    `admin.access`.
  - Documentación de pantalla en `web/features/comunicaciones/COMUNICACIONES-anuncios.md` (desde
    `_system/templates/WEB_SCREEN.md`).

- **No incluye (explícitamente fuera de este bloque):**
  - Cualquier pantalla de `PORTAL_RESIDENTE` que en el futuro embeba este mismo listado como widget
    — esa feature (no diseñada todavía) reusa el hook/cliente de este bloque, no lo duplica.
  - Selector de condominio (asumir que ya existe un mecanismo de condominio activo del contexto de
    la app — si no existe, documentarlo como hallazgo en Notas, no construirlo especulativamente
    aquí).
  - Editor de texto enriquecido para `cuerpo` (PANORAMA §4: texto plano en esta versión).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `announcements.manage`, condominio sin anuncios | Navegar a `/comunicaciones/anuncios` | Estado vacío con CTA "Publicar el primero" |
| 2 | Usuario **sin** `announcements.manage` (ej. residente), condominio sin anuncios | Navegar a `/comunicaciones/anuncios` | Estado vacío **sin** CTA de creación |
| 3 | Usuario con permiso, formulario vacío | Click "Publicar anuncio" → Sheet se abre | Formulario vacío, `fijado` en `false` |
| 4 | Formulario abierto, título y cuerpo válidos | Click "Guardar" | POST exitoso, card nueva aparece en la lista, toast de éxito |
| 5 | Formulario abierto, título vacío | Click "Guardar" | Error de validación Zod, no se hace POST |
| 6 | 3 anuncios: 1 fijado (más antiguo), 2 sin fijar | Cargar la lista | El fijado aparece primero, visualmente distinguido (badge) |
| 7 | Usuario con permiso, anuncio existente | Click "Editar" → cambiar `fijado` a `true` → Guardar | PATCH exitoso, card se reordena al tope, toast de éxito |
| 8 | Usuario **sin** permiso | Ver una card existente | Sin botones de editar/eliminar visibles |
| 9 | Usuario con permiso, anuncio existente | Click "Eliminar" → confirmar en `AlertDialog` | DELETE exitoso, card desaparece, toast de éxito |
| 10 | `AlertDialog` de eliminar abierto | Click "Cancelar" | Se cierra sin eliminar |
| 11 | API no disponible (error de red) | Cualquier acción de escritura | Toast de error, estado previo no se pierde |
| 12 | Usuario autenticado cualquiera | Ver sidebar | Entrada "Comunicaciones" → "Anuncios" visible |

## Contrato

Este bloque **consume** el contrato `LOCK-COMUNICACIONES-01` (producido por `COMUNICACIONES-B01`).
No puede pasar a `ready` sin ese lock vigente. La integración debe respetar exactamente los
request/response definidos en el contrato congelado.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente, o el sustituto documentado si sigue
      bloqueado por `_state/RUNBOOK.md#E-005`) recorriendo los 12 criterios de aceptación, contrastada
      contra PANORAMA §7 (pantallas, componentes, estados).
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-COMUNICACIONES-01`.
- [ ] `web/features/comunicaciones/COMUNICACIONES-anuncios.md` creado desde
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base (`Card`, `Sheet`, `Badge`, `Button`,
      `Textarea`, `Input`, `AlertDialog`, `Switch`) — sin componentes custom nuevos.
- [ ] `web/WEB_API_CLIENT.md` actualizado con el cliente/hook nuevo hacia `announcements`.
- [ ] Entrada de sidebar registrada vía `registerSidebarItem()` — confirmado con captura o
      verificación visual, no solo lectura del código.
- [ ] **`verificacion_critica: true`** (último bloque de la feature, no por riesgo técnico — ver
      `_system/05_DEFINITION_OF_DONE.md` §6): `verify-council` obligatorio antes de que el verifier
      pueda marcar `done`.
- [ ] `_state/CHANGELOG.md` — entrada de cierre de feature agregada (ambos lados, API y Web, en
      `done`).

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Si al ejecutar este bloque no existe todavía un mecanismo de "condominio activo" en el contexto de
> la app (la mayoría de pantallas de `PROPIEDADES`/`DASHBOARD` operan sobre un condominio ya
> seleccionado), documentar cómo se resolvió aquí — no es una decisión nueva de esta feature, es
> reusar el patrón que ya exista.
