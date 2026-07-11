---
tipo: referencia
proyecto: web
feature: DIRECTORIO
actualizado: 2026-07-11
---

# DIRECTORIO — Mi perfil

**Bloque que la produce:** [[../../../features/DIRECTORIO/blocks/DIRECTORIO-B06-pantalla-directorio-contactos]]
**Tipo:** Página
**Ruta:** `/perfil`

## Qué muestra

Formulario de autoservicio con el propio contacto del usuario autenticado (nombre, email,
teléfono) — accesible a cualquier rol, sin permisos especiales (R-DIR-04). No expone
`user_id`/`organization_id` ni ningún dato de otro contacto.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Cargar formulario | `GET /me/contact` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]] |
| Guardar cambios | `PATCH /me/contact` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]] |

## Estados de la vista

- **Carga:** `LoadingState` mientras `useMeContactQuery` resuelve.
- **Error de validación (422):** mensaje inline de Zod (nombre obligatorio, formato de email).
- **Error `CONTACT_NOT_FOUND` (404, defensivo):** teóricamente no debería ocurrir dado el invariante
  de `ADR-001` (todo usuario activo tiene contacto) — si ocurriera, toast de error genérico; no hay
  una pantalla de estado vacío dedicada porque el caso no es alcanzable en la práctica.
- **Éxito:** toast de confirmación + invalidación de la query (`["me-contact"]`).

## Permisos

Ninguno — entrada de sidebar (`sidebar-mi-perfil`) registrada sin `permission`, visible para
cualquier usuario autenticado (incluido `resident`). La protección real es que el endpoint siempre
resuelve el contacto vía `contacts.user_id = auth()->id()`, nunca por un `id` en la URL — no hay
forma de que un usuario vea el perfil de otro desde esta pantalla.
