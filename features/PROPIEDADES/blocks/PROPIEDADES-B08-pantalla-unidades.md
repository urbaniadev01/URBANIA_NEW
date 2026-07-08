---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B08
proyectos: [web]
estado: backlog
depende_de: [PROPIEDADES-B04, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-06
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

> Vacío hasta que el bloque se ejecute.

## Notas

> Las acciones en lote para "Eliminar seleccionadas" deben manejar respuestas mixtas: algunas
> unidades pueden eliminarse (204) y otras fallar (409). La UI debe reportar éxitos y fallos por
> separado, no fallar toda la operación por un 409.
