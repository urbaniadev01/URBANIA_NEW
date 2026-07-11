---
tipo: bloque
proyecto: web
feature: PORTERIA
id: PORTERIA-B03
proyectos: [web]
estado: backlog
depende_de: [PORTERIA-B01, DIRECTORIO-B04, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: true
actualizado: 2026-07-11
---

# PORTERIA-B03 — Pantalla de Correspondencia

## Objetivo

Construir la pantalla "Correspondencia" (`/porteria/correspondencia`): recepción y entrega de
paquetes, integrada con `LOCK-PORTERIA-01`. A diferencia de `PORTERIA-B02`, sí depende del lock de
`DIRECTORIO-B04` (`GET /properties/{id}/occupants`) para el selector de a quién se le entrega el
paquete — no puede pasar a `ready` sin ese lock, aunque `PORTERIA-B01` ya esté `done`.

## Alcance

- **Incluye:**
  - Página `Correspondencia` (`/porteria/correspondencia`): tabla con columnas: unidad,
    transportadora, descripción, hora de recepción, estado (badge "Pendiente"/"Entregado"), acción
    "Entregar" (solo en filas pendientes).
  - Filtro por estado (pendiente/entregado) y por unidad.
  - Sheet "Registrar paquete": selector de unidad, `transportadora` (opcional), `descripcion`
    (requerida). Validación Zod.
  - Sheet/Dialog "Entregar paquete": selector de destinatario poblado desde
    `GET /properties/{id}/occupants` (`LOCK-DIRECTORIO-...`, consumido aquí) — solo ocupantes
    activos de la unidad del paquete, no un buscador libre de contactos. Si la unidad no tiene
    ocupantes registrados, mensaje explícito ("Esta unidad no tiene ocupantes registrados — regístralo
    primero en Directorio") en vez de permitir un destinatario inválido (R-PORT-09).
  - Edición (Sheet) de un paquete pendiente para corregir transportadora/descripción — sin acción de
    edición sobre paquetes ya entregados.
  - Estados: loading (skeleton de tabla), vacío ("No hay paquetes registrados" + CTA "Registrar
    paquete"), error (toast + reintento), éxito (toast de confirmación) — según PANORAMA §7.3.
  - Integración API: hooks/clients para `LOCK-PORTERIA-01` (los 4 endpoints de `packages`) y
    consumo de lectura del endpoint de ocupantes de `DIRECTORIO-B04`.
  - Entrada de sidebar "Portería" → "Correspondencia", vía `registerSidebarItem()`, visible solo con
    `porteria.manage`.
  - Documentación de pantalla en `web/features/porteria/PORTERIA-correspondencia.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Pantalla de Visitantes (`PORTERIA-B02`).
  - Foto de evidencia del paquete (fuera de la feature, ver PANORAMA §3).
  - Cualquier flujo de creación/edición de contactos u ocupantes — si la unidad no tiene ocupantes,
    este bloque solo informa, no redirige a crear uno (eso es responsabilidad de `DIRECTORIO`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Vigilante logueado, condominio sin paquetes | Navegar a `/porteria/correspondencia` | Estado vacío con CTA "Registrar paquete" |
| 2 | Unidad seleccionada, descripción válida | Registrar paquete → Guardar | POST exitoso, fila nueva con estado "Pendiente", toast de éxito |
| 3 | Formulario abierto, descripción vacía | Click "Guardar" | Error de validación Zod, no se hace POST |
| 4 | Paquete pendiente, unidad con 2 ocupantes activos | Click "Entregar" | Selector muestra los 2 ocupantes, no un buscador libre |
| 5 | Paquete pendiente, unidad **sin** ocupantes registrados | Click "Entregar" | Mensaje explícito de "sin ocupantes registrados", sin selector utilizable |
| 6 | Destinatario seleccionado (ocupante válido) | Confirmar entrega | PATCH `/entrega` exitoso, badge cambia a "Entregado", toast de éxito |
| 7 | API devuelve `422` (destinatario inválido, caso borde de carrera) | Confirmar entrega | Toast de error explicando el rechazo, no se cierra el Sheet silenciosamente |
| 8 | Paquete ya entregado | Ver fila | Sin botón "Entregar" |
| 9 | Paquete pendiente | Editar descripción → Guardar | PATCH exitoso, fila actualizada |
| 10 | Paquete ya entregado | Ver fila | Sin botón de editar |
| 11 | 2 pendientes + 1 entregado | Filtrar por "Pendiente" | Solo se muestran los 2 pendientes |
| 12 | Usuario con `porteria.manage` | Ver sidebar | Entrada "Portería" → "Correspondencia" visible |

## Contrato

Este bloque **consume** dos contratos: `LOCK-PORTERIA-01` (producido por `PORTERIA-B01`, para
`packages`) y el lock de `DIRECTORIO-B04` (asignación de ocupantes, para el selector de
destinatario). No puede pasar a `ready` sin **ambos** locks vigentes.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real recorriendo los 12 criterios de aceptación, contrastada contra
      PANORAMA §7.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      ambos locks consumidos.
- [ ] `web/features/porteria/PORTERIA-correspondencia.md` creado desde
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base — sin componentes custom nuevos.
- [ ] `web/WEB_API_CLIENT.md` actualizado con el cliente/hook nuevo hacia `packages` (y el consumo
      de lectura de ocupantes si no fue ya cubierto por un bloque de `DIRECTORIO`).
- [ ] Entrada de sidebar registrada vía `registerSidebarItem()`, gateada por `porteria.manage`.
- [ ] Dado `verificacion_critica: true` (ver nota en `BLOCKS.md`): `verify-council` obligatorio
      antes de que el verifier pueda marcar `done`.
- [ ] `_state/CHANGELOG.md` — entrada de cierre de feature, **si** este bloque es el que
      efectivamente cierra `PORTERIA` (ver Notas).

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Si al ejecutar este bloque `PORTERIA-B02` (Visitantes) ya está `done`, este es el que cierra la
> feature — agregar la entrada de `_state/CHANGELOG.md` de cierre aquí. Si `PORTERIA-B02` sigue
> pendiente, ese cierre lo hace esa tarjeta cuando le toque.
