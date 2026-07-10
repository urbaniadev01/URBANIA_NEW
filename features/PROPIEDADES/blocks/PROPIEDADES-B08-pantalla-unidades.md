---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B08
proyectos: [web]
estado: in_progress
depende_de: [PROPIEDADES-B04, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-09
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

### Pendiente para verificación

- Ejecutar `pnpm ci` (type-check + lint + test + build) en `code/web/`
- Verificación visual Playwright: login → navegar a condominio → tab Unidades → probar todos los criterios

## Notas

> Las acciones en lote para "Eliminar seleccionadas" deben manejar respuestas mixtas: algunas
> unidades pueden eliminarse (204) y otras fallar (409). La UI debe reportar éxitos y fallos por
> separado, no fallar toda la operación por un 409.

> **Auditoría 2026-07-09:** revertido de `done` a `in_progress` — la propia sección "Pendiente para
> verificación" admite que `pnpm ci` y la verificación visual Playwright nunca se ejecutaron, lo cual
> viola `_system/05_DEFINITION_OF_DONE.md`. Requiere completar ambos pasos antes de volver a
> `verifying`.
