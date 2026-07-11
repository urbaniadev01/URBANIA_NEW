---
tipo: referencia
proyecto: web
feature: DIRECTORIO
actualizado: 2026-07-11
---

# DIRECTORIO — Tipos de ocupante

**Bloque que la produce:** [[../../../features/DIRECTORIO/blocks/DIRECTORIO-B05-pantalla-tipos-ocupante]]
**Tipo:** Página
**Ruta:** `/catalogos/tipos-ocupante`

## Qué muestra

Tabla de administración del catálogo `occupant-types` — tipos del sistema (`Propietario`,
`Residente`, `Arrendatario`, `Familiar`) más los personalizados de la organización. Cada fila
muestra nombre, descripción, badge de origen (Sistema/Personalizado) y acciones de editar/eliminar
(solo para los personalizados — los del sistema son de solo lectura).

Reutiliza los componentes compartidos de catálogo introducidos en `PROPIEDADES-B06`
(`CatalogoTable`, `CatalogoDialog`, `DeleteConfirmDialog`) sin reconstruirlos — la entidad
`occupant_types` tiene exactamente la misma forma que `property_types`/`property_statuses`.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Cargar tabla | `GET /occupant-types` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]] |
| Nuevo tipo | `POST /occupant-types` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]] |
| Editar tipo (personalizado) | `PATCH /occupant-types/{id}` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]] |
| Eliminar tipo (personalizado) | `DELETE /occupant-types/{id}` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]] |

## Estados de la vista

- **Carga:** `LoadingState` mientras `useOccupantTypesQuery` resuelve.
- **Vacío:** `EmptyState` con CTA "Crear primero" cuando no hay tipos (caso teórico — el seeder
  siempre deja 4 del sistema).
- **Error de validación (422):** mensaje inline de Zod bajo el campo `nombre` — no se envía la
  request.
- **Error `OCCUPANT_TYPE_NAME_DUPLICATE` (409, crear/editar):** toast de error, diálogo permanece
  abierto con los datos ingresados.
- **Error `SYSTEM_CATALOG_READONLY` (403):** no debería ocurrir desde la UI (los tipos de sistema no
  muestran acciones de editar/eliminar) — si el backend igual lo devuelve, toast de error genérico.
- **Error `OCCUPANT_TYPE_IN_USE` (409, eliminar):** el diálogo de confirmación de borrado muestra el
  mensaje del backend en vez de cerrarse, permitiendo al usuario cancelar.
- **Éxito:** toast de confirmación + invalidación de la query (`["occupant-types"]"`), la tabla se
  refresca sola.

## Permisos

Entrada de sidebar (`sidebar-admin-tipos-ocupante`, grupo "Administración") gateada por el permiso
`admin.access` — mismo permiso que usa el backend para los roles `admin`/`manager` (ver
`RbacDemoSeeder`). Un usuario `resident` no ve esta pantalla en el menú, aunque la ruta en sí no
tiene un guard adicional más allá de `RequireAuth` (la protección real está en el backend: `GET
/occupant-types` es de lectura abierta a cualquier autenticado, pero escritura requiere que el
usuario pertenezca a una organización con tipos propios — ver `LOCK-DIRECTORIO-01`).
