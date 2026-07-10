---
tipo: referencia
proyecto: web
feature: PROPIEDADES
actualizado: 2026-07-08
---

# PROPIEDADES — Administración de Catálogos (Tipos y Estados)

**Bloque que la produce:** [[../../../features/PROPIEDADES/blocks/PROPIEDADES-B06-catalogos-web]]
**Tipo:** Página
**Ruta:** `/catalogos/tipos-propiedad` y `/catalogos/estados-propiedad`

## Qué muestra

Dos pantallas idénticas en estructura para la administración de catálogos del sistema de
propiedades:

- **Tipos de Propiedad** (`/catalogos/tipos-propiedad`): tabla con todos los tipos (sistema +
  tenant). Columnas: nombre, descripción, origen (Sistema/Personalizado), acciones.
- **Estados de Propiedad** (`/catalogos/estados-propiedad`): misma estructura para estados.

Cada pantalla incluye un botón "Nuevo" que abre un diálogo de creación, y cada fila de catálogo
personalizado tiene botones de editar y eliminar. Los catálogos del sistema (`organization_id: null`)
muestran un badge azul "Sistema" y el texto "Solo lectura" en la columna de acciones — no son
editables ni eliminables.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Listar catálogos | GET al montar la página | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]] |
| Crear catálogo | Diálogo con formulario → POST | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]] |
| Editar catálogo | Diálogo precargado → PATCH | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]] |
| Eliminar catálogo | Confirmación → DELETE | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]] |

## Estados de la vista

### Carga
Spinner centrado mientras se obtienen los datos del endpoint GET.

### Vacío
Ilustración con ícono de edificio y texto "No hay elementos registrados". Botón "Crear primero"
que abre el diálogo de creación.

### Error
- **409 `PROPERTY_TYPE_IN_USE` / `PROPERTY_STATUS_IN_USE`**: el diálogo de confirmación de
  eliminación se mantiene abierto y muestra un banner rojo con el mensaje contextual del servidor
  (ej. "Este tipo está en uso por 3 propiedades"). El usuario puede cancelar.
- **409 `*_NAME_DUPLICATE`**: toast de error indicando que el nombre ya existe.
- **403 `SYSTEM_CATALOG_READONLY`**: toast de error indicando que no se puede modificar un catálogo
  del sistema. En operación normal la UI no permite llegar a este estado (los botones no se
  muestran).
- **422 `VALIDATION_ERROR`**: toast con el mensaje del servidor o "Datos inválidos".
- **Error de red / API no disponible**: toast genérico "Error al [crear/actualizar/eliminar]".
  Los datos del formulario no se pierden (el diálogo permanece abierto).

### Éxito
Toast verde confirmando la acción y recarga automática de la tabla (invalidateQueries).

## Permisos

Cualquier usuario autenticado puede leer los catálogos. La creación, edición y eliminación están
sujetas a la autorización del backend (tenant isolation + protección de catálogos del sistema).
La UI refleja estas restricciones ocultando los botones de acción en catálogos del sistema.
