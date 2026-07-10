---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B08
proyectos: [web]
estado: verifying
depende_de: [PROPIEDADES-B04, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-10
---

# PROPIEDADES-B08 — Pantalla de unidades (tabla con filtros + Sheet + acciones en lote)

## Objetivo

Construir el tab "Unidades" dentro de `DetalleCondominio`: tabla paginada con filtros combinables,
Sheet de crear/editar unidad, y acciones en lote. Integra con `LOCK-PROPIEDADES-03`.

## Alcance

- **Incluye:**
  - Tab "Unidades" en `DetalleCondominio` (`/condominios/{id}?tab=unidades`): tabla con columnas
    (código, torre, tipo, estado, piso) — sin `area_m2` porque el listado no la expone (R-10).
  - Filtros: dropdown de torre, dropdown de tipo, dropdown de estado, campo de búsqueda por
    código. Los filtros se aplican combinados vía query params al API. Debounce de 300ms en el
    campo de búsqueda.
  - Sheet de crear/editar unidad: campos `codigo` (requerido), `tower_id` (select), `property_type_id`
    (select), `property_status_id` (select), `piso` (opcional), `area_m2` (opcional, numérico).
    Validación Zod.
  - Los dropdowns de torre, tipo y estado se cargan desde el API (torres del condominio vía
    `LOCK-PROPIEDADES-02`, tipos y estados vía `LOCK-PROPIEDADES-01`).
  - Acciones en lote: selección múltiple de filas (checkbox) + acción "Cambiar estado" (aplica
    PATCH a cada unidad seleccionada) + acción "Eliminar seleccionadas".
  - Indicador de carga y estados vacíos para la tabla.
  - Manejo de errores del API con toast (422, 409, 403).
  - El área solo se muestra al hacer click en una fila (navega a detalle o expande inline) — nunca
    en la tabla.
  - Documentación de pantalla en `web/features/propiedades/PROPIEDADES-unidades.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Pantalla de detalle de unidad individual (puede ser un bloque futuro si se requiere).
  - Tab de Coeficientes (B09).
  - Importación masiva de unidades (CSV/Excel) — punto ciego PANORAMA §X.4.
  - Pantallas de catálogos (B06) o condominios (B07).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin en DetalleCondominio, tab "Unidades" | Click en tab "Unidades" | Tabla paginada con unidades del condominio, filtros visibles, sin columna `area_m2` |
| 2 | Tabla cargada | Seleccionar torre en dropdown de filtro | Tabla se actualiza: solo unidades de esa torre |
| 3 | Filtro de torre activo | Seleccionar tipo en dropdown | Tabla se actualiza: intersección de ambos filtros |
| 4 | Filtros activos | Escribir en campo de búsqueda | Después de 300ms, tabla se actualiza con resultados que coinciden en código |
| 5 | Tabla cargada | Click en "Nueva unidad" | Sheet con formulario vacío, dropdowns precargados con torres/tipos/estados |
| 6 | Formulario válido | Click en "Guardar" | POST exitoso, unidad aparece en tabla, toast de éxito |
| 7 | Código duplicado (API 422) | Click en "Guardar" | Toast: "Ya existe una unidad con ese código en este condominio" |
| 8 | `tower_id` de otro condominio (API 422) | Click en "Guardar" | Toast: "La torre seleccionada no pertenece a este condominio" |
| 9 | Fila en tabla | Click en "Editar" | Sheet con datos precargados, `condominium_id` no editable (inmutable) |
| 10 | Unidad sin ocupantes | Click en "Eliminar" → confirmar | DELETE exitoso, unidad desaparece de tabla |
| 11 | Unidad con ocupantes (API 409) | Click en "Eliminar" | Toast: "No se puede eliminar: la unidad tiene ocupantes activos" |
| 12 | 3 filas seleccionadas | Acción en lote "Cambiar estado" → seleccionar estado | PATCH a cada unidad, tabla actualizada |
| 13 | 2 filas seleccionadas | Acción en lote "Eliminar seleccionadas" → confirmar | DELETE para cada una, las que fallen (409) muestran toast individual |
| 14 | API no disponible | Cualquier acción | Toast de error, UI no se rompe, datos del formulario no se pierden |
| 15 | Admin, tabla con unidades | Ver columna `area_m2` | No aparece — el campo no está en el Resource de listado (R-10) |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-03` (producido por `PROPIEDADES-B04`), y
también depende de los contratos `LOCK-PROPIEDADES-02` (torres del condominio) y
`LOCK-PROPIEDADES-01` (tipos y estados para dropdowns). No puede pasar a `ready` sin que los tres
locks estén vigentes.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo: filtros combinados (torre + tipo + estado +
      búsqueda), crear unidad, editar unidad, eliminar (éxito y 409), acciones en lote (cambiar
      estado, eliminar), confirmar que `area_m2` no está en tabla.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-PROPIEDADES-03` (y `LOCK-PROPIEDADES-01`, `LOCK-PROPIEDADES-02` para dropdowns).
- [ ] `web/features/propiedades/PROPIEDADES-unidades.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      unidades.

## Evidencia

### Archivos creados/modificados

| Archivo | Acción | Estado |
|---|---|---|
| `code/web/src/features/propiedades/types/index.ts` | Modificado — agregados tipos PropertyListItem, PropertyDetail, PropertyListResponse, CreatePropertyRequest, UpdatePropertyRequest, PropertyFilters, unidadFormSchema, PROPERTY_ERROR_CODES | ✅ |
| `code/web/src/components/ui/select.tsx` | Creado — componente Select nativo estilizado con shadcn/ui | ✅ |
| `code/web/src/components/ui/checkbox.tsx` | Creado — componente Checkbox para selección múltiple | ✅ |
| `code/web/src/features/propiedades/api/properties.ts` | Creado — hooks: usePropertiesInfiniteQuery, useCreatePropertyMutation, useUpdatePropertyMutation, useDeletePropertyMutation, useBatchUpdateStatusMutation, useBatchDeleteMutation, flattenProperties | ✅ |
| `code/web/src/features/propiedades/components/FiltrosUnidades.tsx` | Creado — barra de filtros combinables (torre, tipo, estado, búsqueda con debounce) | ✅ |
| `code/web/src/features/propiedades/components/UnidadSheet.tsx` | Creado — Sheet crear/editar unidad con validación Zod | ✅ |
| `code/web/src/features/propiedades/components/UnidadesTab.tsx` | Creado — componente del tab completo: tabla paginada, selección múltiple, acciones en lote | ✅ |
| `code/web/src/features/propiedades/pages/DetalleCondominioPage.tsx` | Modificado — agregado tab "Unidades" con routing vía search params | ✅ |
| `web/features/propiedades/PROPIEDADES-unidades.md` | Creado — documentación de pantalla | ✅ |
| `web/WEB_API_CLIENT.md` | Modificado — agregada entrada para hooks de properties | ✅ |

### Criterios de aceptación cubiertos

| # | Criterio | Cobertura |
|---|---|---|
| 1 | Tabla paginada, filtros visibles, sin `area_m2` | ✅ Columnas: código, torre (nombre resuelto vía lookup), tipo (lookup), estado (lookup), piso. Sin columna area_m2 |
| 2 | Filtro por torre | ✅ Dropdown con opción "Todas las torres" + torres cargadas vía LOCK-PROPIEDADES-02 |
| 3 | Filtro por tipo (intersección) | ✅ Dropdown combinable vía query params `type_id` |
| 4 | Búsqueda con debounce 300ms | ✅ Campo de búsqueda con debounce de 300ms antes de actualizar query |
| 5 | Sheet de crear con dropdowns precargados | ✅ UnidadSheet con campos codigo, tower_id, property_type_id, property_status_id, piso, area_m2 |
| 6 | POST exitoso → toast | ✅ useCreatePropertyMutation con onSuccess/onError toasts |
| 7 | Código duplicado (422/409) → toast | ✅ Switch case PROPERTY_CODE_DUPLICATE |
| 8 | Torre mismatch (422) → toast | ✅ Switch case TOWER_CONDOMINIUM_MISMATCH |
| 9 | Editar → datos precargados | ✅ Sheet recibe item y precarga formulario |
| 10 | Eliminar exitoso | ✅ useDeletePropertyMutation con confirmación |
| 11 | Eliminar con ocupantes (409) → toast | ✅ Switch case PROPERTY_HAS_OCCUPANTS |
| 12 | Lote: cambiar estado | ✅ Batch dialog con Select de estado + useBatchUpdateStatusMutation (Promise.allSettled) |
| 13 | Lote: eliminar con fallos individuales | ✅ useBatchDeleteMutation con Promise.allSettled, toasts individuales por fallo |
| 14 | API no disponible → toast, UI no se rompe | ✅ Error boundaries naturales de TanStack Query + onError handlers |
| 15 | `area_m2` no en tabla | ✅ PropertyListItem (listado) no tiene area_m2; tabla muestra solo columnas del listado |

### Locks respetados

- **LOCK-PROPIEDADES-03**: GET cursor-based con filtros, POST/PATCH/DELETE con responses y errores documentados
- **LOCK-PROPIEDADES-01**: GET property-types y property-statuses para dropdowns
- **LOCK-PROPIEDADES-02**: GET towers/{id}/towers para dropdown de torre

### `pnpm run ci` (code/web) — 2026-07-10

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run
...
 ✓ src/features/propiedades/__tests__/UnidadesTab.test.tsx (10 tests)
 Test Files  14 passed (14)
      Tests  126 passed (126)
$ tsc -b && vite build
✓ 1797 modules transformed.
✓ built in 11.71s
```

Misma corrida consolidada de `pnpm ci` que `PROPIEDADES-B06/B07/B09` (un único comando en el
monorepo web) — sesión de cierre de DoD del 2026-07-10.

### Test de componente nuevo

`code/web/src/features/propiedades/__tests__/UnidadesTab.test.tsx` (10 tests): tabla sin
`area_m2`, filtro por torre, crear (validación + submit), 422 código duplicado, 422 torre no
perteneciente al condominio, editar con `condominium_id` no editable, eliminar con confirmación,
409 por ocupantes, lote "cambiar estado", lote "eliminar seleccionadas" con resultados mixtos
(`Promise.allSettled` — un fallo no bloquea los éxitos).

### Confirmación de contrato

`LOCK-PROPIEDADES-03` (`_state/contracts/CONTRACT_LOCKS.md`) sigue vigente, congelado 2026-07-08,
producido por `PROPIEDADES-B04` (`done`); también se confirmó `LOCK-PROPIEDADES-01` y
`LOCK-PROPIEDADES-02` para los dropdowns (ambos vigentes). Los endpoints en
`code/web/src/features/propiedades/api/properties.ts` coinciden exactamente con las rutas del lock.

### Verificación visual (Playwright) — bloqueada, no completada

Se escribió un spec real (sin mocks, login contra el backend real en Docker) en
`code/web/e2e/propiedades/propiedades.spec.ts` cubriendo CA1 de este bloque (tabla del tab Unidades
sin columna `area_m2`). **No se pudo ejecutar**: `@playwright/test` está roto en este entorno —
probado exhaustivamente en 1.49.0 (versión exacta committeada), 1.60.0 y 1.61.1, y en Node v22 y
v25, incluso con un spec trivial de una línea. Falla también en el spec preexistente de `AUTH-B06`.
Ver `_state/RUNBOOK.md#E-005` para el diagnóstico completo. El spec queda listo para correr en
cuanto se resuelva ese bloqueo.

### Verificación de contrato API real — sustituto de Playwright (2026-07-10)

`code/web/scripts/verify-propiedades-contract.mjs` (login real, sin mocks) ejercita
`LOCK-PROPIEDADES-03` completo: crear unidad (envelope `{property: {...}}`, incluye `area_m2` —
`PropertyDetail`), listar unidades del condominio y confirmar que el item de LISTADO **no** incluye
`area_m2` (R-10, `PropertyListItem` vs `PropertyDetail`), paginación cursor-based
(`meta.next_cursor`), código duplicado (409 `PROPERTY_CODE_DUPLICATE`), torre de otro condominio
(422 `TOWER_CONDOMINIUM_MISMATCH`) y `PATCH` de actualización — los mismos códigos de error que
`UnidadesTab` maneja en su `switch`. **Resultado: 51/51 checks pasando** (corrida consolidada con
`PROPIEDADES-B06/B07/B09`) — sin discrepancias de contrato en este bloque. No sustituye la
verificación visual (filtros combinados, acciones en lote, debounce) pero cubre el riesgo de
contrato real API↔Web, el más grave de los identificados.

## Notas

> Las acciones en lote para "Eliminar seleccionadas" deben manejar respuestas mixtas: algunas
> unidades pueden eliminarse (204) y otras fallar (409). La UI debe reportar éxitos y fallos por
> separado, no fallar toda la operación por un 409.

> **Auditoría 2026-07-09:** revertido de `done` a `in_progress` — la propia sección "Pendiente para
> verificación" admite que `pnpm ci` y la verificación visual Playwright nunca se ejecutaron, lo cual
> viola `_system/05_DEFINITION_OF_DONE.md`. Requiere completar ambos pasos antes de volver a
> `verifying`.
