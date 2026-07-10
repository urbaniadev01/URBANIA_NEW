---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B06
proyectos: [web]
estado: verifying
depende_de: [PROPIEDADES-B02, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-10
---

# PROPIEDADES-B06 — Pantallas de catálogos (TiposPropiedad, EstadosPropiedad)

## Objetivo

Construir las dos pantallas de administración de catálogos en la web: `TiposPropiedad` y
`EstadosPropiedad`. Cada pantalla muestra una tabla con acciones CRUD (crear, editar, eliminar)
usando diálogos, e integra con los endpoints de `LOCK-PROPIEDADES-01`.

## Alcance

- **Incluye:**
  - Página `TiposPropiedad` (`/catalogos/tipos-propiedad`): tabla con columnas (nombre,
    descripción, origen — sistema/personalizado), botón "Nuevo tipo", acciones de editar/eliminar
    por fila.
  - Página `EstadosPropiedad` (`/catalogos/estados-propiedad`): misma estructura que
    TiposPropiedad.
  - Diálogo de crear/editar (Sheet o Dialog de shadcn/ui según `WEB_VISUAL_STANDARDS`) con campos
    `nombre` (requerido) y `descripcion` (opcional).
  - Diálogo de confirmación antes de eliminar, con mensaje contextual ("Este tipo está en uso por
    X propiedades" si el endpoint devuelve 409).
  - Indicador visual de catálogos del sistema (no editables, no eliminables) — badge "Sistema" o
    similar.
  - Integración con API: hooks/clients para consumir `LOCK-PROPIEDADES-01` (`GET /property-types`,
    `POST /property-types`, `PATCH /property-types/{id}`, `DELETE /property-types/{id}`, y sus
    equivalentes para `property-statuses`).
  - Validación Zod en formularios (nombre requerido, longitud mínima/máxima).
  - Manejo de errores del API con toast notifications (422, 403, 409).
  - Documentación de pantalla en `web/features/propiedades/PROPIEDADES-tipos-estados.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Pantallas de condominios (B07), unidades (B08), coeficientes (B09).
  - Pantalla de login/registro (AUTH).
  - Menú de navegación o sidebar — se asume que existe del bootstrap de web o se agrega en este
    bloque como ruta simple. **Auditoría 2026-07-09: esta asunción era falsa** — el bootstrap
    (`WEB_BOOTSTRAP-B01`) no crea un sidebar real; el sidebar navegable recién lo construyó
    `DASHBOARD-B01` (patrón Widget Registry, ver `features/DASHBOARD/PANORAMA.md` §7).
  - Catálogos de otras features (document_types, etc.).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin logueado, API con tipos del sistema | Navegar a `/catalogos/tipos-propiedad` | Tabla con 5 tipos del sistema + los personalizados de la org, badge "Sistema" en los del sistema |
| 2 | Admin, tabla cargada | Click en "Nuevo tipo" | Sheet/Dialog se abre con formulario vacío |
| 3 | Formulario abierto, nombre válido | Click en "Guardar" | POST exitoso, tipo aparece en tabla, toast de éxito |
| 4 | Formulario abierto, nombre vacío | Click en "Guardar" | Error de validación Zod (campo requerido), no se hace POST |
| 5 | Tipo de sistema en tabla | Ver fila | Sin botones de editar/eliminar (o deshabilitados) |
| 6 | Tipo personalizado en tabla | Click en "Editar" | Sheet/Dialog con datos precargados |
| 7 | Edición abierta, datos modificados | Click en "Guardar" | PATCH exitoso, tabla actualizada |
| 8 | Tipo personalizado sin uso | Click en "Eliminar" → confirmar | DELETE exitoso, tipo desaparece de tabla |
| 9 | Tipo en uso por propiedades (API devuelve 409) | Click en "Eliminar" | Toast de error: "No se puede eliminar: está en uso por X propiedades" |
| 10 | API no disponible (error de red) | Cualquier acción | Toast de error genérico, datos no se pierden |
| 11 | Mismo flujo completo para EstadosPropiedad | Navegar a `/catalogos/estados-propiedad` | Mismo comportamiento que tipos (criterios 1–10) |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-01` (producido por `PROPIEDADES-B02`). No
puede pasar a `ready` sin ese lock vigente. La integración debe respetar exactamente los
request/response definidos en el contrato congelado.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright o equivalente) recorriendo los flujos de ambas pantallas:
      crear, editar, eliminar (éxito), eliminar (409 en uso), catálogo de sistema (sin acciones).
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-PROPIEDADES-01`.
- [ ] `web/features/propiedades/PROPIEDADES-tipos-estados.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      catálogos.

## Evidencia

### `pnpm run ci` (code/web) — 2026-07-10

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run
...
 ✓ src/features/propiedades/__tests__/TiposPropiedadPage.test.tsx (10 tests)
 ✓ src/features/propiedades/__tests__/EstadosPropiedadPage.test.tsx (10 tests)
 Test Files  14 passed (14)
      Tests  126 passed (126)
$ tsc -b && vite build
✓ 1797 modules transformed.
✓ built in 11.71s
```

Salida completa (todos los archivos, no solo los de este bloque) en la sesión de cierre de DoD del
2026-07-10 — ver también `PROPIEDADES-B07/B08/B09` que comparten la misma corrida de `pnpm ci`
(un único monorepo web, un único comando).

### Tests de componente nuevos

- `code/web/src/features/propiedades/__tests__/TiposPropiedadPage.test.tsx` (10 tests): render con
  badges Sistema/Personalizado, abrir diálogo, validación Zod (nombre vacío), crear con payload
  correcto, editar con id+payload correctos, warning de 409 IN_USE sin cerrar el diálogo.
- `code/web/src/features/propiedades/__tests__/EstadosPropiedadPage.test.tsx` (10 tests): mismo
  patrón para Estados de Propiedad.

### Confirmación de contrato

`LOCK-PROPIEDADES-01` (`_state/contracts/CONTRACT_LOCKS.md`) sigue vigente, congelado 2026-07-08,
producido por `PROPIEDADES-B02` (`done`). Los endpoints usados en
`code/web/src/features/propiedades/api/property-types.ts` y `property-statuses.ts` coinciden
exactamente con las rutas del lock (`GET/POST/PATCH/DELETE /api/v1/property-types` y
`/api/v1/property-statuses`).

### Verificación visual (Playwright) — bloqueada, no completada

Se escribió un spec real (sin mocks, login contra el backend real en Docker) en
`code/web/e2e/propiedades/propiedades.spec.ts` cubriendo CA1-CA5 y CA11 de este bloque. **No se pudo
ejecutar**: `@playwright/test` está roto en este entorno — probado exhaustivamente en 1.49.0 (versión
exacta committeada), 1.60.0 y 1.61.1, y en Node v22 y v25, incluso con un spec trivial de una línea
sin ningún import del proyecto. Falla también en el spec preexistente de `AUTH-B06`. Ver
`_state/RUNBOOK.md#E-005` para el diagnóstico completo. El spec queda listo para correr en cuanto se
resuelva ese bloqueo.

### Verificación de contrato API real — sustituto de Playwright (2026-07-10)

Como alternativa que sí se pudo ejecutar, se escribió y corrió
`code/web/scripts/verify-propiedades-contract.mjs` — un script sin mocks que hace login real contra
el backend (Docker) y ejercita los endpoints de `LOCK-PROPIEDADES-01` exactamente como los llama el
frontend, verificando el shape de cada respuesta contra los tipos TS (`CatalogoItem`,
`CatalogoListResponse`, etc.) y los códigos de error que `TiposPropiedadPage`/`EstadosPropiedadPage`
manejan en sus `switch`. **Resultado: 51/51 checks pasando** (ver evidencia consolidada también en
`PROPIEDADES-B07/B08/B09`, que comparten la misma corrida del script).

Esta verificación encontró y permitió corregir un bug real: `POST`/`PATCH` de `property-types` y
`property-statuses` devolvían `{property_type: {...}}` en vez de `{data: {...}}` (violaba el
contrato congelado y rompía el toast de éxito en producción con un `TypeError`). Ver
`_state/RUNBOOK.md#E-006` y la nota correspondiente en
`PROPIEDADES-B02#Notas` (bloque productor, ya `done`, donde vivía el bug).

No sustituye completamente la verificación visual (no cubre renderizado, routing de navegador, CSS)
pero cubre el riesgo más grave: contrato real API↔Web, que los tests de componente (con la API
mockeada) no pueden detectar.

## Notas

> Los catálogos del sistema (`organization_id = NULL`) no son editables ni eliminables. El badge
> "Sistema" se determina por el campo `organization_id` que viene en la respuesta del API. Si el API
> no expone ese campo en el listado, este bloque necesita que B02 lo incluya en el Resource.

> **Auditoría 2026-07-09:** revertido de `done` a `in_progress` — la sección Evidencia no cumple
> `_system/05_DEFINITION_OF_DONE.md` (evidencia vacía). Requiere correr `pnpm ci` real y
> verificación visual Playwright de los criterios de aceptación antes de volver a `verifying`.
