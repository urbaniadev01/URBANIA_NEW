---
tipo: bloque
proyecto: web
feature: DIRECTORIO
id: DIRECTORIO-B07
proyectos: [web]
estado: backlog
depende_de: [DIRECTORIO-B04, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-08
---

# DIRECTORIO-B07 — Pantalla de asignación de ocupantes por unidad

## Objetivo

Construir la pantalla que permite a un admin/staff asignar y desasignar contactos a una unidad
específica, con su tipo de ocupante, integrada con `LOCK-DIRECTORIO-03`. Se accede desde el detalle
de una unidad (`PROPIEDADES-B08`, pantalla de unidades) — este bloque agrega la sección de ocupantes
a esa vista existente, no crea una ruta nueva independiente.

## Alcance

- **Incluye:**
  - Sección "Ocupantes" en el detalle de unidad (`/condominios/{id}/propiedades/{propertyId}` de
    `PROPIEDADES-B08`): tabla con contactos asignados (nombre, tipo de ocupante, indicador de
    `es_principal`), botón "Asignar ocupante".
  - Diálogo "Asignar ocupante": selector de contacto existente (búsqueda tipo combobox contra `GET
    /contacts?search=`) + selector de tipo de ocupante (`GET /occupant-types`) + checkbox
    `es_principal`.
  - Acción de desasignar (con confirmación) por fila.
  - Acción de editar tipo/`es_principal` de una asignación existente.
  - Manejo del caso "no hay contacto todavía": enlace directo a "Crear contacto nuevo" que abre el
    diálogo de `DIRECTORIO-B06` sin salir del flujo (o navega a `/directorio/contactos` — decidir en
    implementación cuál da mejor UX, documentarlo en la ficha de pantalla).
  - Manejo de errores del API con toast (409 `OCCUPANT_ASSIGNMENT_DUPLICATE`, 422, 403, 404).
  - Documentación de pantalla en `web/features/directorio/DIRECTORIO-asignacion-ocupantes.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de contactos (`DIRECTORIO-B06`) — este bloque solo busca/selecciona contactos existentes.
  - CRUD de tipos de ocupante (`DIRECTORIO-B05`).
  - Cualquier cambio a la pantalla de unidades de `PROPIEDADES-B08` más allá de agregar esta sección
    — no se toca su lógica de edición de la unidad en sí.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin en detalle de unidad con ocupantes | Ver sección "Ocupantes" | Tabla con contactos asignados, tipo, indicador de principal |
| 2 | Admin, sección cargada | Click en "Asignar ocupante" | Diálogo se abre con selector de contacto y tipo |
| 3 | Diálogo abierto, contacto y tipo seleccionados | Click en "Asignar" | POST exitoso, ocupante aparece en la tabla |
| 4 | Mismo contacto+tipo ya asignado (API devuelve 409) | Click en "Asignar" | Toast: "Este contacto ya está asignado con ese tipo" |
| 5 | Marcar `es_principal` en un tipo que ya tiene principal | Click en "Asignar" con `es_principal` marcado | POST exitoso, el ocupante anterior pierde el indicador de principal en la tabla (refresco tras la respuesta) |
| 6 | Ocupante en tabla | Click en "Editar" → cambiar tipo → "Guardar" | PATCH exitoso, tabla actualizada |
| 7 | Ocupante en tabla | Click en "Desasignar" → confirmar | DELETE exitoso, desaparece de la tabla |
| 8 | Buscador de contacto sin resultados | Escribir un nombre inexistente | Mensaje "Sin resultados" + enlace a crear contacto nuevo |
| 9 | API no disponible (error de red) | Cualquier acción | Toast de error genérico, datos no se pierden |

## Contrato

Este bloque **consume** el contrato `LOCK-DIRECTORIO-03` (producido por `DIRECTORIO-B04`). No puede
pasar a `ready` sin ese lock vigente. También consume, de forma read-only, `LOCK-DIRECTORIO-01`
(`GET /occupant-types`) y `LOCK-DIRECTORIO-02` (`GET /contacts`) para poblar los selectores.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo los 9 casos de la tabla de criterios.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-DIRECTORIO-03` (y el uso read-only de `LOCK-DIRECTORIO-01`/`02`).
- [ ] `web/features/directorio/DIRECTORIO-asignacion-ocupantes.md` creado desde
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos.
- [ ] Confirmar que `PROPIEDADES-B08` ya está `done` antes de empezar — este bloque depende de esa
      pantalla existiendo, aunque `BLOCKS.md`/`BOARD.md` no lo modelen como dependencia mecánica de
      bloque (es una dependencia de UI, no de contrato de API).

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Dependencia real pero no mecánica: esta pantalla se inserta dentro de la vista de detalle de unidad
> de `PROPIEDADES-B08`. `depende_de` en el frontmatter solo declara `DIRECTORIO-B04` (el lock de API)
> porque ese es el gate mecánico que el sistema de bloques rastrea — la dependencia de UI sobre
> `PROPIEDADES-B08` es una nota operativa para quien ejecute este bloque, no algo que
> `_state/contracts/CONTRACT_LOCKS.md` pueda expresar (esa tabla solo rastrea contratos de API, no
> orden de pantallas Web).
