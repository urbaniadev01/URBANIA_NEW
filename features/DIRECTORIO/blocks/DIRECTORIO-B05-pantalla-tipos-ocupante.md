---
tipo: bloque
proyecto: web
feature: DIRECTORIO
id: DIRECTORIO-B05
proyectos: [web]
estado: verifying
depende_de: [DIRECTORIO-B02, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-11
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

- [x] `pnpm ci` ejecutado (por pasos — ver nota de `pnpm run lint` en bloques anteriores, ya resuelto)
      — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo los 9 casos de la tabla de criterios.
      **Bloqueado** — `@playwright/test` sigue roto en este entorno (`_state/RUNBOOK.md#E-005`),
      mismo bloqueo que `PROPIEDADES-B06..B09`. Sustituido por 10 tests de componente reales (no
      mocks de red completos — mockean solo los hooks de API, ejercitan el DOM real) que cubren los
      9 criterios. Pendiente de revisión visual manual del usuario antes de `done`.
- [x] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-DIRECTORIO-01`.
- [x] `web/features/directorio/DIRECTORIO-tipos-ocupante.md` creado desde
      `_system/templates/WEB_SCREEN.md`.
- [x] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01` — de hecho
      reutiliza directamente los componentes de catálogo ya existentes de `PROPIEDADES-B06`
      (`CatalogoTable`, `CatalogoDialog`, `DeleteConfirmDialog`), sin crear ninguno nuevo.
- [x] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos.

## Evidencia

### Implementación

Mismo patrón exacto que `TiposPropiedadPage` (`PROPIEDADES-B06`), reutilizando sus componentes
compartidos de catálogo (`CatalogoTable`/`CatalogoDialog`/`DeleteConfirmDialog`) en vez de
reconstruirlos — la entidad `occupant_types` tiene exactamente la misma forma que
`property_types`/`property_statuses` (id, organization_id, nombre, descripcion, created_by,
updated_by, timestamps), así que `src/features/directorio/types/index.ts` reexporta los tipos
genéricos de `@/features/propiedades/types` en vez de duplicarlos, agregando solo los códigos de
error propios de `/occupant-types`. `src/features/directorio/api/occupant-types.ts`
(`useOccupantTypesQuery`/`useCreate.../useUpdate.../useDelete...`), `TiposOcupantePage.tsx`, ruta
`/catalogos/tipos-ocupante` registrada en `App.tsx`, entrada de sidebar (`sidebar-admin-tipos-
ocupante`, grupo "Administración", permiso `admin.access`) vía el patrón Widget Registry
(`src/features/directorio/dashboard.ts` + línea de import en `bootstrap.ts`) — no una asunción de
que "ya existe", corrigiendo la nota de alcance de la propia tarjeta.

### Tests de componente (10 tests, `src/features/directorio/__tests__/TiposOcupantePage.test.tsx`)

```
$ npx vitest run src/features/directorio/__tests__/TiposOcupantePage.test.tsx
Test Files  1 passed (1)
     Tests  10 passed (10)
```

Cubren los 9 criterios de aceptación: tabla con badges Sistema/Personalizado (CA1), abrir diálogo
"Nuevo" (CA2), crear con payload correcto (CA3), bloqueo de validación Zod con nombre vacío (CA4),
sin acciones en filas de sistema (CA5), precarga + actualizar en edición (CA6), confirmar +
eliminar (CA7), warning inline en el diálogo cuando el DELETE devuelve 409 `OCCUPANT_TYPE_IN_USE`
sin cerrar el diálogo (CA8), estados de carga/vacío (CA9 — falta de red se maneja igual que
`PROPIEDADES-B06`, vía el `onError` genérico de TanStack Query + toast, ya cubierto por el patrón
compartido).

### `pnpm ci` (por pasos)

```
$ npx tsc -b
(sin salida — limpio)

$ npx eslint . --max-warnings 0
(sin salida — limpio)

$ npx vitest run
Test Files  15 passed (15)
     Tests  136 passed (136)

$ npx vite build
✓ 1840 modules transformed
✓ built in 53.30s
```

136 = 126 tests anteriores (post `PROPIEDADES-B09`) + 10 nuevos de este bloque. Sin regresiones.

**Bug preexistente encontrado y corregido (no relacionado con este bloque):** `src/app/App.test.tsx`
afirmaba `"Buenos días, Test User"` pero el saludo real del dashboard es `"Hola, {nombre}"` desde la
tercera pasada de rediseño visual (2026-07-10, ver nota en `_state/BOARD.md` — el test nunca se
actualizó en esa sesión). Corregido el assert; sin cambios de comportamiento en código de producto.

### Archivos creados

- `src/features/directorio/types/index.ts`
- `src/features/directorio/api/occupant-types.ts`
- `src/features/directorio/pages/TiposOcupantePage.tsx`
- `src/features/directorio/dashboard.ts`
- `src/features/directorio/__tests__/TiposOcupantePage.test.tsx`
- `web/features/directorio/DIRECTORIO-tipos-ocupante.md`

### Archivos modificados

- `src/app/App.tsx` — ruta `/catalogos/tipos-ocupante`.
- `src/app/bootstrap.ts` — import de `@/features/directorio/dashboard`.
- `src/app/App.test.tsx` — fix del assert de saludo desactualizado.
- `web/WEB_API_CLIENT.md` — sección de hooks de Directorio.
- `_state/BOARD.md` — estado del bloque.

## Notas

> Mismo patrón exacto que `PROPIEDADES-B06` — si esa pantalla ya definió un componente reusable de
> "tabla de catálogo con badge de sistema", este bloque debe reutilizarlo en vez de reconstruirlo.

> **Verificación visual real (2026-07-11) — Playwright MCP, navegador real, sin mocks.** Login real
> (`admin@urbania.test`), navegación a `/catalogos/tipos-ocupante`: tabla con los 4 tipos de sistema
> (Arrendatario, Familiar, Propietario, Residente — badge "Sistema", solo lectura) y un tipo
> personalizado con acciones Editar/Eliminar. Sin errores de consola en esta pantalla. Evidencia:
> `directorio-b05-tipos-ocupante.png`. Screen queda con su verificación visual real completa — la
> transición a `done` sigue siendo decisión del usuario (ver `CLAUDE.md`).
