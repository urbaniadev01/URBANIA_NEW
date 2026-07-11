---
tipo: referencia
proyecto: web
feature: DIRECTORIO
actualizado: 2026-07-11
---

# DIRECTORIO — Directorio de contactos

**Bloque que la produce:** [[../../../features/DIRECTORIO/blocks/DIRECTORIO-B06-pantalla-directorio-contactos]]
**Tipo:** Página + Drawer de detalle
**Ruta:** `/directorio/contactos`

## Qué muestra

Tabla administrativa de contactos de la organización: nombre, vínculo (badge "Con cuenta"/"Sin
cuenta" según `user_id`), acceso a las unidades asociadas, y acciones de editar/eliminar. Buscador
con debounce (300ms) que consulta el backend vía `?search=`. Click en una fila (o en "Ver
unidades") abre un drawer de solo lectura con el detalle del contacto y sus unidades
(`GET /contacts/{id}/properties`) — no permite asignar/desasignar desde acá, eso es
`DIRECTORIO-B07`.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Cargar tabla / buscar | `GET /contacts?search=` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]] |
| Nuevo contacto | `POST /contacts` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]] |
| Editar contacto | `PATCH /contacts/{id}` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]] |
| Eliminar contacto | `DELETE /contacts/{id}` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]] |
| Ver detalle (drawer) | `GET /contacts/{id}/properties` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]] |

## Estados de la vista

- **Carga:** `LoadingState` mientras `useContactsQuery` resuelve.
- **Vacío (sin búsqueda):** `EmptyState` con CTA "Crear primero".
- **Vacío (con búsqueda activa):** mensaje contextual con el término buscado + CTA "Limpiar
  búsqueda".
- **Error de validación (422):** mensaje inline de Zod (nombre obligatorio, formato de email) — no
  se envía la request.
- **Error `CONTACT_HAS_OCCUPATIONS` (409, eliminar):** el diálogo de confirmación muestra "Este
  contacto tiene unidades asignadas, quítalas primero" en vez de cerrarse.
- **Éxito:** toast de confirmación + invalidación de la query (`["contacts"]`).
- **Sin permiso de gestión (403, teórico):** el backend ya deniega el listado completo a actores sin
  scope `organization`/`condominium`/`tower` — la Web no implementa un estado especial para esto más
  allá del manejo de error genérico, dado que la pantalla está gateada en el sidebar por
  `admin.access`.

## Permisos

Entrada de sidebar (`sidebar-admin-contactos`, grupo "Administración") gateada por `admin.access` —
mismo criterio que el resto de pantallas administrativas de PROPIEDADES/DIRECTORIO. La tabla en sí
nunca recibe `email`/`telefono` de actores sin scope `organization` (R-DIR-06, aplicado del lado del
API en `LOCK-DIRECTORIO-02`) — como esos actores no ven esta pantalla en el menú (gate de
`admin.access`), no hace falta un manejo especial en la UI para ocultar columnas que el backend ya
no envía.
