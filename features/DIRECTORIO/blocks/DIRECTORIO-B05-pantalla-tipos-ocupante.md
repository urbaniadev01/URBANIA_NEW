---
tipo: bloque
proyecto: web
feature: DIRECTORIO
id: DIRECTORIO-B05
proyectos: [web]
estado: backlog
depende_de: [DIRECTORIO-B02, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-08
---

# DIRECTORIO-B05 — Pantalla de catálogo (TiposOcupante)

## Objetivo

Construir la pantalla de administración del catálogo `occupant-types`, integrada con
`LOCK-DIRECTORIO-01`. Mismo patrón exacto que `PROPIEDADES-B06` (catálogos de tipos/estados de
propiedad), aplicado a un único catálogo.

## Alcance

- **Incluye:**
  - Página `TiposOcupante` (`/catalogos/tipos-ocupante`): tabla con columnas (nombre, descripción,
    origen — sistema/personalizado), botón "Nuevo tipo", acciones de editar/eliminar por fila.
  - Diálogo de crear/editar (Sheet/Dialog de shadcn/ui) con campos `nombre` (requerido) y
    `descripcion` (opcional).
  - Diálogo de confirmación antes de eliminar, con mensaje contextual si el endpoint devuelve 409
    (`OCCUPANT_TYPE_IN_USE`).
  - Indicador visual de catálogo de sistema (badge "Sistema", sin acciones de editar/eliminar).
  - Integración con API: hooks/clients para `LOCK-DIRECTORIO-01` (`GET/POST/PATCH/DELETE
    /occupant-types`).
  - Validación Zod en formulario (nombre requerido).
  - Manejo de errores del API con toast (422, 403, 409).
  - Documentación de pantalla en `web/features/directorio/DIRECTORIO-tipos-ocupante.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Pantalla de directorio de contactos (`DIRECTORIO-B06`) o de asignación de ocupantes
    (`DIRECTORIO-B07`).
  - Menú de navegación/sidebar nuevo — se asume ya existente desde `PROPIEDADES-B06`/bootstrap, esta
    pantalla solo agrega su entrada. **Auditoría 2026-07-09: esta asunción era falsa** — ni el
    bootstrap ni `PROPIEDADES-B06` crean un sidebar real; el mecanismo real es el patrón Widget
    Registry construido por `DASHBOARD-B01` (`registerSidebarItem()` + import en `bootstrap.ts`,
    ver `features/DASHBOARD/PANORAMA.md` §7 y el nuevo ítem del DoD Web en
    `_system/05_DEFINITION_OF_DONE.md` §3). Este bloque debe registrar su propia entrada de sidebar
    con ese mecanismo cuando se ejecute, no asumir que "ya existe".

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin logueado, API con tipos del sistema | Navegar a `/catalogos/tipos-ocupante` | Tabla con 4 tipos del sistema + los personalizados de la org, badge "Sistema" en los del sistema |
| 2 | Admin, tabla cargada | Click en "Nuevo tipo" | Sheet/Dialog se abre con formulario vacío |
| 3 | Formulario abierto, nombre válido | Click en "Guardar" | POST exitoso, tipo aparece en tabla, toast de éxito |
| 4 | Formulario abierto, nombre vacío | Click en "Guardar" | Error de validación Zod, no se hace POST |
| 5 | Tipo de sistema en tabla | Ver fila | Sin botones de editar/eliminar (o deshabilitados) |
| 6 | Tipo personalizado en tabla | Click en "Editar" → modificar → "Guardar" | PATCH exitoso, tabla actualizada |
| 7 | Tipo personalizado sin uso | Click en "Eliminar" → confirmar | DELETE exitoso, tipo desaparece de tabla |
| 8 | Tipo en uso por ocupantes (API devuelve 409) | Click en "Eliminar" | Toast de error: "No se puede eliminar: está en uso" |
| 9 | API no disponible (error de red) | Cualquier acción | Toast de error genérico, datos no se pierden |

## Contrato

Este bloque **consume** el contrato `LOCK-DIRECTORIO-01` (producido por `DIRECTORIO-B02`). No puede
pasar a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo los 9 casos de la tabla de criterios.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-DIRECTORIO-01`.
- [ ] `web/features/directorio/DIRECTORIO-tipos-ocupante.md` creado desde
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Mismo patrón exacto que `PROPIEDADES-B06` — si esa pantalla ya definió un componente reusable de
> "tabla de catálogo con badge de sistema", este bloque debe reutilizarlo en vez de reconstruirlo.
