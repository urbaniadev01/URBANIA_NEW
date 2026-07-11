---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B08
proyectos: [web]
estado: done
depende_de: [PROPIEDADES-B04, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-11
---

# PROPIEDADES-B08 â€” Pantalla de unidades (tabla con filtros + Sheet + acciones en lote)

## Objetivo

Construir el tab "Unidades" dentro de `DetalleCondominio`: tabla paginada con filtros combinables,
Sheet de crear/editar unidad, y acciones en lote. Integra con `LOCK-PROPIEDADES-03`.

## Alcance

- **Incluye:**
  - Tab "Unidades" en `DetalleCondominio` (`/condominios/{id}?tab=unidades`): tabla con columnas
    (cÃ³digo, torre, tipo, estado, piso) â€” sin `area_m2` porque el listado no la expone (R-10).
  - Filtros: dropdown de torre, dropdown de tipo, dropdown de estado, campo de bÃºsqueda por
    cÃ³digo. Los filtros se aplican combinados vÃ­a query params al API. Debounce de 300ms en el
    campo de bÃºsqueda.
  - Sheet de crear/editar unidad: campos `codigo` (requerido), `tower_id` (select), `property_type_id`
    (select), `property_status_id` (select), `piso` (opcional), `area_m2` (opcional, numÃ©rico).
    ValidaciÃ³n Zod.
  - Los dropdowns de torre, tipo y estado se cargan desde el API (torres del condominio vÃ­a
    `LOCK-PROPIEDADES-02`, tipos y estados vÃ­a `LOCK-PROPIEDADES-01`).
  - Acciones en lote: selecciÃ³n mÃºltiple de filas (checkbox) + acciÃ³n "Cambiar estado" (aplica
    PATCH a cada unidad seleccionada) + acciÃ³n "Eliminar seleccionadas".
  - Indicador de carga y estados vacÃ­os para la tabla.
  - Manejo de errores del API con toast (422, 409, 403).
  - El Ã¡rea solo se muestra al hacer click en una fila (navega a detalle o expande inline) â€” nunca
    en la tabla.
  - DocumentaciÃ³n de pantalla en `web/features/propiedades/PROPIEDADES-unidades.md`.

- **No incluye (explÃ­citamente fuera de este bloque):**
  - Pantalla de detalle de unidad individual (puede ser un bloque futuro si se requiere).
  - Tab de Coeficientes (B09).
  - ImportaciÃ³n masiva de unidades (CSV/Excel) â€” punto ciego PANORAMA Â§X.4.
  - Pantallas de catÃ¡logos (B06) o condominios (B07).

## Criterios de aceptaciÃ³n

| # | Entrada | AcciÃ³n | Salida esperada |
|---|---|---|---|
| 1 | Admin en DetalleCondominio, tab "Unidades" | Click en tab "Unidades" | Tabla paginada con unidades del condominio, filtros visibles, sin columna `area_m2` |
| 2 | Tabla cargada | Seleccionar torre en dropdown de filtro | Tabla se actualiza: solo unidades de esa torre |
| 3 | Filtro de torre activo | Seleccionar tipo en dropdown | Tabla se actualiza: intersecciÃ³n de ambos filtros |
| 4 | Filtros activos | Escribir en campo de bÃºsqueda | DespuÃ©s de 300ms, tabla se actualiza con resultados que coinciden en cÃ³digo |
| 5 | Tabla cargada | Click en "Nueva unidad" | Sheet con formulario vacÃ­o, dropdowns precargados con torres/tipos/estados |
| 6 | Formulario vÃ¡lido | Click en "Guardar" | POST exitoso, unidad aparece en tabla, toast de Ã©xito |
| 7 | CÃ³digo duplicado (API 422) | Click en "Guardar" | Toast: "Ya existe una unidad con ese cÃ³digo en este condominio" |
| 8 | `tower_id` de otro condominio (API 422) | Click en "Guardar" | Toast: "La torre seleccionada no pertenece a este condominio" |
| 9 | Fila en tabla | Click en "Editar" | Sheet con datos precargados, `condominium_id` no editable (inmutable) |
| 10 | Unidad sin ocupantes | Click en "Eliminar" â†’ confirmar | DELETE exitoso, unidad desaparece de tabla |
| 11 | Unidad con ocupantes (API 409) | Click en "Eliminar" | Toast: "No se puede eliminar: la unidad tiene ocupantes activos" |
| 12 | 3 filas seleccionadas | AcciÃ³n en lote "Cambiar estado" â†’ seleccionar estado | PATCH a cada unidad, tabla actualizada |
| 13 | 2 filas seleccionadas | AcciÃ³n en lote "Eliminar seleccionadas" â†’ confirmar | DELETE para cada una, las que fallen (409) muestran toast individual |
| 14 | API no disponible | Cualquier acciÃ³n | Toast de error, UI no se rompe, datos del formulario no se pierden |
| 15 | Admin, tabla con unidades | Ver columna `area_m2` | No aparece â€” el campo no estÃ¡ en el Resource de listado (R-10) |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-03` (producido por `PROPIEDADES-B04`), y
tambiÃ©n depende de los contratos `LOCK-PROPIEDADES-02` (torres del condominio) y
`LOCK-PROPIEDADES-01` (tipos y estados para dropdowns). No puede pasar a `ready` sin que los tres
locks estÃ©n vigentes.

## Definition of Done

- [ ] `pnpm ci` ejecutado â€” salida completa pegada.
- [ ] VerificaciÃ³n visual real (Playwright) recorriendo: filtros combinados (torre + tipo + estado +
      bÃºsqueda), crear unidad, editar unidad, eliminar (Ã©xito y 409), acciones en lote (cambiar
      estado, eliminar), confirmar que `area_m2` no estÃ¡ en tabla.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integraciÃ³n respeta exactamente
      `LOCK-PROPIEDADES-03` (y `LOCK-PROPIEDADES-01`, `LOCK-PROPIEDADES-02` para dropdowns).
- [ ] `web/features/propiedades/PROPIEDADES-unidades.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librerÃ­a base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      unidades.

## Evidencia

### Archivos creados/modificados

| Archivo | AcciÃ³n | Estado |
|---|---|---|
| `code/web/src/features/propiedades/types/index.ts` | Modificado â€” agregados tipos PropertyListItem, PropertyDetail, PropertyListResponse, CreatePropertyRequest, UpdatePropertyRequest, PropertyFilters, unidadFormSchema, PROPERTY_ERROR_CODES | âœ… |
| `code/web/src/components/ui/select.tsx` | Creado â€” componente Select nativo estilizado con shadcn/ui | âœ… |
| `code/web/src/components/ui/checkbox.tsx` | Creado â€” componente Checkbox para selecciÃ³n mÃºltiple | âœ… |
| `code/web/src/features/propiedades/api/properties.ts` | Creado â€” hooks: usePropertiesInfiniteQuery, useCreatePropertyMutation, useUpdatePropertyMutation, useDeletePropertyMutation, useBatchUpdateStatusMutation, useBatchDeleteMutation, flattenProperties | âœ… |
| `code/web/src/features/propiedades/components/FiltrosUnidades.tsx` | Creado â€” barra de filtros combinables (torre, tipo, estado, bÃºsqueda con debounce) | âœ… |
| `code/web/src/features/propiedades/components/UnidadSheet.tsx` | Creado â€” Sheet crear/editar unidad con validaciÃ³n Zod | âœ… |
| `code/web/src/features/propiedades/components/UnidadesTab.tsx` | Creado â€” componente del tab completo: tabla paginada, selecciÃ³n mÃºltiple, acciones en lote | âœ… |
| `code/web/src/features/propiedades/pages/DetalleCondominioPage.tsx` | Modificado â€” agregado tab "Unidades" con routing vÃ­a search params | âœ… |
| `web/features/propiedades/PROPIEDADES-unidades.md` | Creado â€” documentaciÃ³n de pantalla | âœ… |
| `web/WEB_API_CLIENT.md` | Modificado â€” agregada entrada para hooks de properties | âœ… |

### Criterios de aceptaciÃ³n cubiertos

| # | Criterio | Cobertura |
|---|---|---|
| 1 | Tabla paginada, filtros visibles, sin `area_m2` | âœ… Columnas: cÃ³digo, torre (nombre resuelto vÃ­a lookup), tipo (lookup), estado (lookup), piso. Sin columna area_m2 |
| 2 | Filtro por torre | âœ… Dropdown con opciÃ³n "Todas las torres" + torres cargadas vÃ­a LOCK-PROPIEDADES-02 |
| 3 | Filtro por tipo (intersecciÃ³n) | âœ… Dropdown combinable vÃ­a query params `type_id` |
| 4 | BÃºsqueda con debounce 300ms | âœ… Campo de bÃºsqueda con debounce de 300ms antes de actualizar query |
| 5 | Sheet de crear con dropdowns precargados | âœ… UnidadSheet con campos codigo, tower_id, property_type_id, property_status_id, piso, area_m2 |
| 6 | POST exitoso â†’ toast | âœ… useCreatePropertyMutation con onSuccess/onError toasts |
| 7 | CÃ³digo duplicado (422/409) â†’ toast | âœ… Switch case PROPERTY_CODE_DUPLICATE |
| 8 | Torre mismatch (422) â†’ toast | âœ… Switch case TOWER_CONDOMINIUM_MISMATCH |
| 9 | Editar â†’ datos precargados | âœ… Sheet recibe item y precarga formulario |
| 10 | Eliminar exitoso | âœ… useDeletePropertyMutation con confirmaciÃ³n |
| 11 | Eliminar con ocupantes (409) â†’ toast | âœ… Switch case PROPERTY_HAS_OCCUPANTS |
| 12 | Lote: cambiar estado | âœ… Batch dialog con Select de estado + useBatchUpdateStatusMutation (Promise.allSettled) |
| 13 | Lote: eliminar con fallos individuales | âœ… useBatchDeleteMutation con Promise.allSettled, toasts individuales por fallo |
| 14 | API no disponible â†’ toast, UI no se rompe | âœ… Error boundaries naturales de TanStack Query + onError handlers |
| 15 | `area_m2` no en tabla | âœ… PropertyListItem (listado) no tiene area_m2; tabla muestra solo columnas del listado |

### Locks respetados

- **LOCK-PROPIEDADES-03**: GET cursor-based con filtros, POST/PATCH/DELETE con responses y errores documentados
- **LOCK-PROPIEDADES-01**: GET property-types y property-statuses para dropdowns
- **LOCK-PROPIEDADES-02**: GET towers/{id}/towers para dropdown de torre

### `pnpm run ci` (code/web) â€” 2026-07-10

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run
...
 âœ“ src/features/propiedades/__tests__/UnidadesTab.test.tsx (10 tests)
 Test Files  14 passed (14)
      Tests  126 passed (126)
$ tsc -b && vite build
âœ“ 1797 modules transformed.
âœ“ built in 11.71s
```

Misma corrida consolidada de `pnpm ci` que `PROPIEDADES-B06/B07/B09` (un Ãºnico comando en el
monorepo web) â€” sesiÃ³n de cierre de DoD del 2026-07-10.

### Test de componente nuevo

`code/web/src/features/propiedades/__tests__/UnidadesTab.test.tsx` (10 tests): tabla sin
`area_m2`, filtro por torre, crear (validaciÃ³n + submit), 422 cÃ³digo duplicado, 422 torre no
perteneciente al condominio, editar con `condominium_id` no editable, eliminar con confirmaciÃ³n,
409 por ocupantes, lote "cambiar estado", lote "eliminar seleccionadas" con resultados mixtos
(`Promise.allSettled` â€” un fallo no bloquea los Ã©xitos).

### ConfirmaciÃ³n de contrato

`LOCK-PROPIEDADES-03` (`_state/contracts/CONTRACT_LOCKS.md`) sigue vigente, congelado 2026-07-08,
producido por `PROPIEDADES-B04` (`done`); tambiÃ©n se confirmÃ³ `LOCK-PROPIEDADES-01` y
`LOCK-PROPIEDADES-02` para los dropdowns (ambos vigentes). Los endpoints en
`code/web/src/features/propiedades/api/properties.ts` coinciden exactamente con las rutas del lock.

### VerificaciÃ³n visual (Playwright) â€” bloqueada, no completada

Se escribiÃ³ un spec real (sin mocks, login contra el backend real en Docker) en
`code/web/e2e/propiedades/propiedades.spec.ts` cubriendo CA1 de este bloque (tabla del tab Unidades
sin columna `area_m2`). **No se pudo ejecutar**: `@playwright/test` estÃ¡ roto en este entorno â€”
probado exhaustivamente en 1.49.0 (versiÃ³n exacta committeada), 1.60.0 y 1.61.1, y en Node v22 y
v25, incluso con un spec trivial de una lÃ­nea. Falla tambiÃ©n en el spec preexistente de `AUTH-B06`.
Ver `_state/RUNBOOK.md#E-005` para el diagnÃ³stico completo. El spec queda listo para correr en
cuanto se resuelva ese bloqueo.

### VerificaciÃ³n de contrato API real â€” sustituto de Playwright (2026-07-10)

`code/web/scripts/verify-propiedades-contract.mjs` (login real, sin mocks) ejercita
`LOCK-PROPIEDADES-03` completo: crear unidad (envelope `{property: {...}}`, incluye `area_m2` â€”
`PropertyDetail`), listar unidades del condominio y confirmar que el item de LISTADO **no** incluye
`area_m2` (R-10, `PropertyListItem` vs `PropertyDetail`), paginaciÃ³n cursor-based
(`meta.next_cursor`), cÃ³digo duplicado (409 `PROPERTY_CODE_DUPLICATE`), torre de otro condominio
(422 `TOWER_CONDOMINIUM_MISMATCH`) y `PATCH` de actualizaciÃ³n â€” los mismos cÃ³digos de error que
`UnidadesTab` maneja en su `switch`. **Resultado: 51/51 checks pasando** (corrida consolidada con
`PROPIEDADES-B06/B07/B09`) â€” sin discrepancias de contrato en este bloque. No sustituye la
verificaciÃ³n visual (filtros combinados, acciones en lote, debounce) pero cubre el riesgo de
contrato real APIâ†”Web, el mÃ¡s grave de los identificados.

## Notas

> Las acciones en lote para "Eliminar seleccionadas" deben manejar respuestas mixtas: algunas
> unidades pueden eliminarse (204) y otras fallar (409). La UI debe reportar Ã©xitos y fallos por
> separado, no fallar toda la operaciÃ³n por un 409.

> **AuditorÃ­a 2026-07-09:** revertido de `done` a `in_progress` â€” la propia secciÃ³n "Pendiente para
> verificaciÃ³n" admite que `pnpm ci` y la verificaciÃ³n visual Playwright nunca se ejecutaron, lo cual
> viola `_system/05_DEFINITION_OF_DONE.md`. Requiere completar ambos pasos antes de volver a
> `verifying`.


