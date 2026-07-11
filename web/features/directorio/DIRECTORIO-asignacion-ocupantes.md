---
tipo: referencia
proyecto: web
feature: DIRECTORIO
actualizado: 2026-07-11
---

# DIRECTORIO — Asignación de ocupantes por unidad

**Bloque que la produce:** [[../../../features/DIRECTORIO/blocks/DIRECTORIO-B07-pantalla-asignacion-ocupantes]]
**Tipo:** Sheet + 2 Dialogs, insertados desde la tabla de unidades de `PROPIEDADES-B08`
**Ruta:** No introduce una ruta nueva — se abre desde `/condominios/{id}?tab=unidades`

## Desviación de alcance respecto a la tarjeta (documentada explícitamente)

La tarjeta asumía una "vista de detalle de unidad" (`/condominios/{id}/propiedades/{propertyId}`)
sobre la cual insertar esta sección. Esa ruta **no existe** — `PROPIEDADES-B08` gestiona las
unidades inline en `UnidadesTab` (tabla con `Sheet` de crear/editar, sin página de detalle
independiente). En vez de forzar una IA que no existe o crear una página de detalle nueva (fuera del
alcance declarado: "no se toca su lógica de edición de la unidad en sí"), se agregó un botón
"Ocupantes" (ícono `Users`) por fila en `UnidadesTab` que abre `OcupantesSheet` — mismo resultado
funcional (gestionar ocupantes por unidad) sin inventar una pantalla nueva no pedida ni romper la IA
existente. Mismo criterio que la corrección de la asunción de sidebar en `DIRECTORIO-B05`.

## Qué muestra

`OcupantesSheet`: lista de ocupantes activos de la unidad (nombre del contacto, tipo de ocupante,
badge "Principal" si aplica), botón "Asignar ocupante", acciones de editar/desasignar por fila.

`AssignOccupantDialog`: campo de búsqueda de contacto (debounce 300ms contra `GET /contacts?search=`,
lock read-only `LOCK-DIRECTORIO-02`) que muestra resultados como lista seleccionable; una vez
seleccionado el contacto, selector de tipo de ocupante (`GET /occupant-types`, lock read-only
`LOCK-DIRECTORIO-01`) y checkbox "Marcar como ocupante principal". Sin resultados de búsqueda:
mensaje + enlace a `/directorio/contactos` para crear el contacto (se optó por navegar en vez de
abrir el diálogo de creación embebido, para no anidar dos flujos de formulario — decisión de UX
documentada acá según lo pedido por la tarjeta).

`EditOccupantDialog`: edita `occupant_type_id`/`es_principal` de una asignación existente — el
contacto no es editable (para cambiar de contacto hay que desasignar y volver a asignar).

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Cargar ocupantes | `GET /properties/{id}/occupants` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-03]] |
| Buscar contacto (al asignar) | `GET /contacts?search=` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]] (read-only) |
| Cargar tipos de ocupante | `GET /occupant-types` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]] (read-only) |
| Asignar ocupante | `POST /properties/{id}/occupants` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-03]] |
| Editar asignación | `PATCH /property-occupants/{id}` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-03]] |
| Desasignar | `DELETE /property-occupants/{id}` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-03]] |

## Estados de la vista

- **Carga:** `LoadingState` mientras `usePropertyOccupantsQuery` resuelve.
- **Vacío:** `EmptyState` "No hay ocupantes asignados a esta unidad."
- **Sin resultados de búsqueda de contacto:** mensaje + enlace a crear contacto nuevo.
- **Error `OCCUPANT_ASSIGNMENT_DUPLICATE` (409, asignar/editar):** toast "Este contacto ya está
  asignado con ese tipo" — el diálogo permanece abierto.
- **`es_principal` (R-DIR-07):** el backend desmarca automáticamente el principal anterior al asignar
  uno nuevo — la UI no hace nada especial, simplemente refresca la lista tras la respuesta
  (`invalidateQueries`), que ya refleja el estado correcto del servidor.
- **Éxito:** toast de confirmación + invalidación de `["property-occupants", propertyId]`.
- **Error de red:** toast de error genérico vía el manejo `onError` de cada mutación.

## Permisos

No agrega una entrada de sidebar nueva — se accede desde `UnidadesTab`, ya gateada por la
navegación existente a `/condominios/{id}`. La protección real de quién puede asignar/editar/
desasignar ocupantes vive en el backend (`LOCK-DIRECTORIO-03`: scope `organization`/`condominium`/
`tower`, un `residente` recibe `403`) — la Web no duplica ese chequeo, solo maneja el error si
ocurre.
