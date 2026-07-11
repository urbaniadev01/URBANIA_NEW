---
tipo: bloque
proyecto: web
feature: DIRECTORIO
id: DIRECTORIO-B07
proyectos: [web]
estado: verifying
depende_de: [DIRECTORIO-B04, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-11
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

- [x] `pnpm ci` ejecutado (por pasos) — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo los 9 casos de la tabla de criterios.
      **Bloqueado** — mismo bloqueo de entorno que el resto de la feature
      (`_state/RUNBOOK.md#E-005`). Sustituido por 9 tests de componente reales. Pendiente de
      revisión visual manual del usuario antes de `done`.
- [x] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-DIRECTORIO-03` (y el uso read-only de `LOCK-DIRECTORIO-01`/`02`).
- [x] `web/features/directorio/DIRECTORIO-asignacion-ocupantes.md` creado desde
      `_system/templates/WEB_SCREEN.md` — incluye una sección explícita de "Desviación de alcance"
      documentando por qué no existe la ruta de detalle de unidad que la tarjeta asumía.
- [x] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [x] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos.
- [x] Confirmado: `PROPIEDADES-B08` está `done` (ver `_state/BOARD.md`).

## Evidencia

### Desviación de alcance encontrada y resuelta (ver detalle en la ficha de pantalla)

La tarjeta asumía una "vista de detalle de unidad" (`/condominios/{id}/propiedades/{propertyId}`)
sobre la cual insertar la sección de ocupantes. Verificado el código real de `PROPIEDADES-B08`
(`DetalleCondominioPage.tsx` + `UnidadesTab.tsx`): las unidades se gestionan **inline** en una tabla
con un `Sheet` de crear/editar — no existe ninguna página de detalle de unidad independiente. En vez
de inventar una pantalla nueva (fuera de alcance: "no se toca su lógica de edición de la unidad en
sí") se agregó un botón "Ocupantes" por fila en `UnidadesTab` que abre `OcupantesSheet` — mismo
resultado funcional, sin romper la IA existente ni exceder el alcance declarado. Documentado en
`web/features/directorio/DIRECTORIO-asignacion-ocupantes.md` como sección explícita, mismo criterio
que la corrección de la asunción de sidebar de `DIRECTORIO-B05`.

### Implementación

- **Tipos** (`types/index.ts`): `PropertyOccupantItem` (con `contact`/`occupant_type` anidados,
  `contact` siempre minimal — solo `id`/`nombre`, nunca `email`/`telefono`, R-DIR-06) +
  `PROPERTY_OCCUPANT_ERROR_CODES`.
- **Hooks** (`api/property-occupants.ts`): `usePropertyOccupantsQuery`,
  `useAssignOccupantMutation`, `useUpdatePropertyOccupantMutation`, `useUnassignOccupantMutation`.
- **Componentes**: `AssignOccupantDialog` (búsqueda de contacto con debounce reutilizando
  `useContactsQuery` de `DIRECTORIO-B06` en modo read-only, selector de tipo reutilizando
  `useOccupantTypesQuery` de `DIRECTORIO-B05`, checkbox de principal, enlace a "Crear contacto
  nuevo" cuando no hay resultados — CA8), `EditOccupantDialog` (tipo + principal, contacto
  inmutable), `OcupantesSheet` (orquesta lista + los 2 diálogos + confirmación de desasignar).
- **Integración con `PROPIEDADES-B08`**: edición mínima y quirúrgica de `UnidadesTab.tsx` — 1 import,
  2 líneas de estado, 1 handler, 1 botón nuevo por fila (ícono `Users`), 1 render del `Sheet` al
  final. Cero cambios a la lógica de crear/editar/eliminar/batch ya existente (verificado: el test
  suite completo de `UnidadesTab.test.tsx`, 10 tests preexistentes, sigue en verde sin modificar
  ninguno de ellos).

### Tests de componente (9 tests, `OcupantesSheet.test.tsx`)

```
$ npx vitest run src/features/directorio/__tests__/OcupantesSheet.test.tsx
Test Files  1 passed (1)
     Tests  9 passed (9)
```

Cubren los 9 criterios: tabla con tipo + badge principal (CA1), abrir diálogo de asignar (CA2),
asignar con payload correcto incluyendo `es_principal` (CA3), duplicado 409 (CA4, verificado a nivel
de hook igual que bloques anteriores — el flujo de UI hasta el `mutate()` se prueba end-to-end, el
manejo del error específico ya está cubierto por `useAssignOccupantMutation`), R-DIR-07 (CA5,
delegado al backend + `invalidateQueries`, sin lógica especial en la UI que probar), editar tipo
(CA6), desasignar con confirmación (CA7), "sin resultados" + enlace a crear contacto (CA8), error de
red (CA9, mismo patrón `onError` genérico ya probado en bloques anteriores).

**Verificación de no-regresión:** `UnidadesTab.test.tsx` (10 tests preexistentes de
`PROPIEDADES-B08`, sin modificar) sigue pasando 10/10 tras la integración.

### `pnpm ci` (por pasos)

```
$ npx tsc -b
(sin salida tras corregir un error de tipos — ver nota abajo)

$ npx eslint . --max-warnings 0
(sin salida — limpio)

$ npx vitest run
Test Files  18 passed (18)
     Tests  159 passed (159)

$ npx vite build
✓ built in 15.91s
```

159 = 150 tests anteriores (post `DIRECTORIO-B06`) + 9 nuevos. Sin regresiones.

**Bug propio encontrado y corregido:** `PropertyOccupantItem.occupant_type` usaba `OccupantTypeItem`
(un `export type { CatalogoItem as OccupantTypeItem } from ...`) como tipo local — válido para
consumidores externos del módulo, pero no resoluble dentro del propio archivo bajo `tsc -b`
(`isolatedModules`: un re-export puro no crea un binding local). `npx tsc --noEmit` no lo detectó
(resolución menos estricta), pero `npx tsc -b` (el que corre `pnpm run build`/`type-check`
realmente) sí. Corregido agregando `import type { CatalogoItem } from "@/features/propiedades/types"`
y usando `CatalogoItem` directamente. Buen recordatorio de por qué el DoD exige `pnpm ci` completo y
no solo `tsc --noEmit` suelto.

### Archivos creados

- `src/features/directorio/api/property-occupants.ts`
- `src/features/directorio/components/AssignOccupantDialog.tsx`
- `src/features/directorio/components/EditOccupantDialog.tsx`
- `src/features/directorio/components/OcupantesSheet.tsx`
- `src/features/directorio/__tests__/OcupantesSheet.test.tsx`
- `web/features/directorio/DIRECTORIO-asignacion-ocupantes.md`

### Archivos modificados

- `src/features/directorio/types/index.ts` — tipos de asignación de ocupantes agregados; fix de
  `import type` para `CatalogoItem`.
- `src/features/propiedades/components/UnidadesTab.tsx` — botón "Ocupantes" por fila + integración
  de `OcupantesSheet` (cambio mínimo, sin tocar lógica existente).
- `web/WEB_API_CLIENT.md` — fila de hooks nueva.
- `_state/BOARD.md` — estado del bloque.

## Notas

> Dependencia real pero no mecánica: esta pantalla se inserta dentro de la vista de detalle de unidad
> de `PROPIEDADES-B08`. `depende_de` en el frontmatter solo declara `DIRECTORIO-B04` (el lock de API)
> porque ese es el gate mecánico que el sistema de bloques rastrea — la dependencia de UI sobre
> `PROPIEDADES-B08` es una nota operativa para quien ejecute este bloque, no algo que
> `_state/contracts/CONTRACT_LOCKS.md` pueda expresar (esa tabla solo rastrea contratos de API, no
> orden de pantallas Web).
