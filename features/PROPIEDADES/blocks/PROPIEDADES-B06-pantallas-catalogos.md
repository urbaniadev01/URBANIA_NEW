---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B06
proyectos: [web]
estado: done
depende_de: [PROPIEDADES-B02, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-11
---

# PROPIEDADES-B06 â€” Pantallas de catÃ¡logos (TiposPropiedad, EstadosPropiedad)

## Objetivo

Construir las dos pantallas de administraciÃ³n de catÃ¡logos en la web: `TiposPropiedad` y
`EstadosPropiedad`. Cada pantalla muestra una tabla con acciones CRUD (crear, editar, eliminar)
usando diÃ¡logos, e integra con los endpoints de `LOCK-PROPIEDADES-01`.

## Alcance

- **Incluye:**
  - PÃ¡gina `TiposPropiedad` (`/catalogos/tipos-propiedad`): tabla con columnas (nombre,
    descripciÃ³n, origen â€” sistema/personalizado), botÃ³n "Nuevo tipo", acciones de editar/eliminar
    por fila.
  - PÃ¡gina `EstadosPropiedad` (`/catalogos/estados-propiedad`): misma estructura que
    TiposPropiedad.
  - DiÃ¡logo de crear/editar (Sheet o Dialog de shadcn/ui segÃºn `WEB_VISUAL_STANDARDS`) con campos
    `nombre` (requerido) y `descripcion` (opcional).
  - DiÃ¡logo de confirmaciÃ³n antes de eliminar, con mensaje contextual ("Este tipo estÃ¡ en uso por
    X propiedades" si el endpoint devuelve 409).
  - Indicador visual de catÃ¡logos del sistema (no editables, no eliminables) â€” badge "Sistema" o
    similar.
  - IntegraciÃ³n con API: hooks/clients para consumir `LOCK-PROPIEDADES-01` (`GET /property-types`,
    `POST /property-types`, `PATCH /property-types/{id}`, `DELETE /property-types/{id}`, y sus
    equivalentes para `property-statuses`).
  - ValidaciÃ³n Zod en formularios (nombre requerido, longitud mÃ­nima/mÃ¡xima).
  - Manejo de errores del API con toast notifications (422, 403, 409).
  - DocumentaciÃ³n de pantalla en `web/features/propiedades/PROPIEDADES-tipos-estados.md`.

- **No incluye (explÃ­citamente fuera de este bloque):**
  - Pantallas de condominios (B07), unidades (B08), coeficientes (B09).
  - Pantalla de login/registro (AUTH).
  - MenÃº de navegaciÃ³n o sidebar â€” se asume que existe del bootstrap de web o se agrega en este
    bloque como ruta simple. **AuditorÃ­a 2026-07-09: esta asunciÃ³n era falsa** â€” el bootstrap
    (`WEB_BOOTSTRAP-B01`) no crea un sidebar real; el sidebar navegable reciÃ©n lo construyÃ³
    `DASHBOARD-B01` (patrÃ³n Widget Registry, ver `features/DASHBOARD/PANORAMA.md` Â§7).
  - CatÃ¡logos de otras features (document_types, etc.).

## Criterios de aceptaciÃ³n

| # | Entrada | AcciÃ³n | Salida esperada |
|---|---|---|---|
| 1 | Admin logueado, API con tipos del sistema | Navegar a `/catalogos/tipos-propiedad` | Tabla con 5 tipos del sistema + los personalizados de la org, badge "Sistema" en los del sistema |
| 2 | Admin, tabla cargada | Click en "Nuevo tipo" | Sheet/Dialog se abre con formulario vacÃ­o |
| 3 | Formulario abierto, nombre vÃ¡lido | Click en "Guardar" | POST exitoso, tipo aparece en tabla, toast de Ã©xito |
| 4 | Formulario abierto, nombre vacÃ­o | Click en "Guardar" | Error de validaciÃ³n Zod (campo requerido), no se hace POST |
| 5 | Tipo de sistema en tabla | Ver fila | Sin botones de editar/eliminar (o deshabilitados) |
| 6 | Tipo personalizado en tabla | Click en "Editar" | Sheet/Dialog con datos precargados |
| 7 | EdiciÃ³n abierta, datos modificados | Click en "Guardar" | PATCH exitoso, tabla actualizada |
| 8 | Tipo personalizado sin uso | Click en "Eliminar" â†’ confirmar | DELETE exitoso, tipo desaparece de tabla |
| 9 | Tipo en uso por propiedades (API devuelve 409) | Click en "Eliminar" | Toast de error: "No se puede eliminar: estÃ¡ en uso por X propiedades" |
| 10 | API no disponible (error de red) | Cualquier acciÃ³n | Toast de error genÃ©rico, datos no se pierden |
| 11 | Mismo flujo completo para EstadosPropiedad | Navegar a `/catalogos/estados-propiedad` | Mismo comportamiento que tipos (criterios 1â€“10) |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-01` (producido por `PROPIEDADES-B02`). No
puede pasar a `ready` sin ese lock vigente. La integraciÃ³n debe respetar exactamente los
request/response definidos en el contrato congelado.

## Definition of Done

- [ ] `pnpm ci` ejecutado â€” salida completa pegada.
- [ ] VerificaciÃ³n visual real (Playwright o equivalente) recorriendo los flujos de ambas pantallas:
      crear, editar, eliminar (Ã©xito), eliminar (409 en uso), catÃ¡logo de sistema (sin acciones).
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integraciÃ³n respeta exactamente
      `LOCK-PROPIEDADES-01`.
- [ ] `web/features/propiedades/PROPIEDADES-tipos-estados.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librerÃ­a base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      catÃ¡logos.

## Evidencia

### `pnpm run ci` (code/web) â€” 2026-07-10

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run
...
 âœ“ src/features/propiedades/__tests__/TiposPropiedadPage.test.tsx (10 tests)
 âœ“ src/features/propiedades/__tests__/EstadosPropiedadPage.test.tsx (10 tests)
 Test Files  14 passed (14)
      Tests  126 passed (126)
$ tsc -b && vite build
âœ“ 1797 modules transformed.
âœ“ built in 11.71s
```

Salida completa (todos los archivos, no solo los de este bloque) en la sesiÃ³n de cierre de DoD del
2026-07-10 â€” ver tambiÃ©n `PROPIEDADES-B07/B08/B09` que comparten la misma corrida de `pnpm ci`
(un Ãºnico monorepo web, un Ãºnico comando).

### Tests de componente nuevos

- `code/web/src/features/propiedades/__tests__/TiposPropiedadPage.test.tsx` (10 tests): render con
  badges Sistema/Personalizado, abrir diÃ¡logo, validaciÃ³n Zod (nombre vacÃ­o), crear con payload
  correcto, editar con id+payload correctos, warning de 409 IN_USE sin cerrar el diÃ¡logo.
- `code/web/src/features/propiedades/__tests__/EstadosPropiedadPage.test.tsx` (10 tests): mismo
  patrÃ³n para Estados de Propiedad.

### ConfirmaciÃ³n de contrato

`LOCK-PROPIEDADES-01` (`_state/contracts/CONTRACT_LOCKS.md`) sigue vigente, congelado 2026-07-08,
producido por `PROPIEDADES-B02` (`done`). Los endpoints usados en
`code/web/src/features/propiedades/api/property-types.ts` y `property-statuses.ts` coinciden
exactamente con las rutas del lock (`GET/POST/PATCH/DELETE /api/v1/property-types` y
`/api/v1/property-statuses`).

### VerificaciÃ³n visual (Playwright) â€” bloqueada, no completada

Se escribiÃ³ un spec real (sin mocks, login contra el backend real en Docker) en
`code/web/e2e/propiedades/propiedades.spec.ts` cubriendo CA1-CA5 y CA11 de este bloque. **No se pudo
ejecutar**: `@playwright/test` estÃ¡ roto en este entorno â€” probado exhaustivamente en 1.49.0 (versiÃ³n
exacta committeada), 1.60.0 y 1.61.1, y en Node v22 y v25, incluso con un spec trivial de una lÃ­nea
sin ningÃºn import del proyecto. Falla tambiÃ©n en el spec preexistente de `AUTH-B06`. Ver
`_state/RUNBOOK.md#E-005` para el diagnÃ³stico completo. El spec queda listo para correr en cuanto se
resuelva ese bloqueo.

### VerificaciÃ³n de contrato API real â€” sustituto de Playwright (2026-07-10)

Como alternativa que sÃ­ se pudo ejecutar, se escribiÃ³ y corriÃ³
`code/web/scripts/verify-propiedades-contract.mjs` â€” un script sin mocks que hace login real contra
el backend (Docker) y ejercita los endpoints de `LOCK-PROPIEDADES-01` exactamente como los llama el
frontend, verificando el shape de cada respuesta contra los tipos TS (`CatalogoItem`,
`CatalogoListResponse`, etc.) y los cÃ³digos de error que `TiposPropiedadPage`/`EstadosPropiedadPage`
manejan en sus `switch`. **Resultado: 51/51 checks pasando** (ver evidencia consolidada tambiÃ©n en
`PROPIEDADES-B07/B08/B09`, que comparten la misma corrida del script).

Esta verificaciÃ³n encontrÃ³ y permitiÃ³ corregir un bug real: `POST`/`PATCH` de `property-types` y
`property-statuses` devolvÃ­an `{property_type: {...}}` en vez de `{data: {...}}` (violaba el
contrato congelado y rompÃ­a el toast de Ã©xito en producciÃ³n con un `TypeError`). Ver
`_state/RUNBOOK.md#E-006` y la nota correspondiente en
`PROPIEDADES-B02#Notas` (bloque productor, ya `done`, donde vivÃ­a el bug).

No sustituye completamente la verificaciÃ³n visual (no cubre renderizado, routing de navegador, CSS)
pero cubre el riesgo mÃ¡s grave: contrato real APIâ†”Web, que los tests de componente (con la API
mockeada) no pueden detectar.

## Notas

> Los catÃ¡logos del sistema (`organization_id = NULL`) no son editables ni eliminables. El badge
> "Sistema" se determina por el campo `organization_id` que viene en la respuesta del API. Si el API
> no expone ese campo en el listado, este bloque necesita que B02 lo incluya en el Resource.

> **AuditorÃ­a 2026-07-09:** revertido de `done` a `in_progress` â€” la secciÃ³n Evidencia no cumple
> `_system/05_DEFINITION_OF_DONE.md` (evidencia vacÃ­a). Requiere correr `pnpm ci` real y
> verificaciÃ³n visual Playwright de los criterios de aceptaciÃ³n antes de volver a `verifying`.


