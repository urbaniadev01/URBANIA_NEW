---
tipo: bloque
proyecto: web
feature: PORTERIA
id: PORTERIA-B02
proyectos: [web]
estado: backlog
depende_de: [PORTERIA-B01, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: true
actualizado: 2026-07-11
---

# PORTERIA-B02 — Pantalla de Visitantes

## Objetivo

Construir la pantalla "Visitantes" (`/porteria/visitantes`): minuta digital de ingresos/salidas,
integrada con `LOCK-PORTERIA-01`. No depende de `DIRECTORIO` — el destino de una visita es solo la
unidad (selector de propiedad, ya resuelto por `PROPIEDADES`).

## Alcance

- **Incluye:**
  - Página `Visitantes` (`/porteria/visitantes`): tabla (componente `table` base de shadcn/ui, sin
    la convención `DataTable` todavía — ver PANORAMA §7.2) con columnas: unidad, visitante,
    documento, placa, hora de ingreso, estado (badge "Activa"/"Cerrada"), acción "Registrar salida"
    (solo en filas activas).
  - Filtro por estado (activa/cerrada) y por unidad.
  - Sheet "Registrar ingreso": selector de unidad (`Combobox` sobre el listado de `properties` del
    condominio), `visitante_nombre`, `visitante_documento`, `vehiculo_placa` (opcional). Validación
    Zod (nombre/documento requeridos).
  - Acción "Registrar salida" por fila, con `AlertDialog` de confirmación (irreversible en la
    práctica).
  - Edición (Sheet) de una visita activa para corregir nombre/documento/placa — sin acción de
    edición sobre visitas cerradas.
  - Estados: loading (skeleton de tabla), vacío ("No hay visitas registradas hoy" + CTA "Registrar
    ingreso"), error (toast + reintento), éxito (toast de confirmación) — según PANORAMA §7.3.
  - Integración API: hooks/clients para `LOCK-PORTERIA-01` (los 4 endpoints de `visits`).
  - Entrada de sidebar "Portería" → "Visitantes", vía `registerSidebarItem()`, visible solo con
    `porteria.manage`.
  - Documentación de pantalla en `web/features/porteria/PORTERIA-visitantes.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Pantalla de Correspondencia (`PORTERIA-B03`).
  - Cualquier vista de residente.
  - Selector de ocupante/contacto — esta pantalla no lo necesita (a diferencia de Correspondencia).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Vigilante logueado, condominio sin visitas | Navegar a `/porteria/visitantes` | Estado vacío con CTA "Registrar ingreso" |
| 2 | Formulario vacío | Click "Registrar ingreso" → Sheet se abre | Selector de unidad + campos vacíos |
| 3 | Unidad seleccionada, nombre y documento válidos | Click "Guardar" | POST exitoso, fila nueva aparece con estado "Activa", toast de éxito |
| 4 | Formulario abierto, documento vacío | Click "Guardar" | Error de validación Zod, no se hace POST |
| 5 | Fila con visita activa | Click "Registrar salida" → confirmar | PATCH `/salida` exitoso, badge cambia a "Cerrada", botón de salida desaparece |
| 6 | `AlertDialog` de salida abierto | Click "Cancelar" | Se cierra sin registrar salida |
| 7 | 2 visitas activas + 1 cerrada | Filtrar por "Activa" | Solo se muestran las 2 activas |
| 8 | Visita activa | Click "Editar" → corregir documento → Guardar | PATCH exitoso, tabla actualizada |
| 9 | Visita cerrada | Ver fila | Sin botón de editar (o deshabilitado) |
| 10 | API no disponible | Cualquier acción de escritura | Toast de error, estado previo no se pierde |
| 11 | Usuario con `porteria.manage` | Ver sidebar | Entrada "Portería" → "Visitantes" visible |
| 12 | Usuario **sin** `porteria.manage` | Ver sidebar / intentar navegar directo a la ruta | Entrada no visible en sidebar; ruta protegida (redirección o 403 en UI) |

## Contrato

Este bloque **consume** el contrato `LOCK-PORTERIA-01` (producido por `PORTERIA-B01`). No puede
pasar a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente, o el sustituto documentado si sigue
      bloqueado por `_state/RUNBOOK.md#E-005`) recorriendo los 12 criterios de aceptación,
      contrastada contra PANORAMA §7.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-PORTERIA-01`.
- [ ] `web/features/porteria/PORTERIA-visitantes.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base — sin componentes custom nuevos.
- [ ] `web/WEB_API_CLIENT.md` actualizado con el cliente/hook nuevo hacia `visits`.
- [ ] Entrada de sidebar registrada vía `registerSidebarItem()`, gateada por `porteria.manage`.
- [ ] Dado `verificacion_critica: true` (ver nota en `BLOCKS.md` sobre cuál de los dos bloques web
      cierra realmente la feature): `verify-council` obligatorio antes de que el verifier pueda
      marcar `done`.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Si al ejecutar este bloque `PORTERIA-B03` (Correspondencia) ya está `done`, este es el que cierra
> la feature — agregar la entrada de `_state/CHANGELOG.md` de cierre de feature aquí en vez de en
> B03. Si `PORTERIA-B03` sigue bloqueado por `DIRECTORIO-B04`, ese cierre queda pendiente para
> cuando B03 se ejecute.
