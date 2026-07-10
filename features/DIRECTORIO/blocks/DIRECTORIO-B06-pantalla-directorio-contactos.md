---
tipo: bloque
proyecto: web
feature: DIRECTORIO
id: DIRECTORIO-B06
proyectos: [web]
estado: backlog
depende_de: [DIRECTORIO-B03, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-08
---

# DIRECTORIO-B06 — Pantalla de directorio de contactos + "Mi perfil"

## Objetivo

Construir la pantalla administrativa de directorio de contactos (`/directorio/contactos`) y la
pantalla de autoservicio "Mi perfil" (`/perfil`), ambas integradas con `LOCK-DIRECTORIO-02`.

## Alcance

- **Incluye:**
  - Página `Contactos` (`/directorio/contactos`): tabla con columnas (nombre, tipo de vínculo —
    "con cuenta"/"sin cuenta" según `user_id`, unidades asociadas), buscador (`?search=`), botón
    "Nuevo contacto", acciones de editar/eliminar por fila.
  - Diálogo de crear/editar contacto (Sheet/Dialog) con campos `nombre` (requerido), `email`,
    `telefono` (opcionales) — sin campo `user_id` (el endpoint no lo acepta, ver
    `DIRECTORIO-B03`).
  - Diálogo de confirmación antes de eliminar, con mensaje contextual si el endpoint devuelve 409
    (`CONTACT_HAS_OCCUPATIONS`: "Este contacto tiene unidades asignadas, quítalas primero").
  - Drawer de detalle de contacto: datos + lista de unidades asociadas (`GET
    /contacts/{id}/properties`).
  - Página `Mi perfil` (`/perfil`): formulario de solo el propio contacto (`GET`/`PATCH
    /me/contact`), accesible a cualquier usuario autenticado sin importar su rol.
  - Ocultamiento de `email`/`telefono` en la tabla de directorio cuando el actor no tiene permiso de
    gestión de contactos (coherente con R-DIR-06 del lado del API — la Web no debe intentar mostrar
    un dato que el API ya no envía).
  - Validación Zod en formularios (nombre requerido, formato de email si se ingresa).
  - Manejo de errores del API con toast (422, 403, 404, 409).
  - Documentación de pantallas en `web/features/directorio/DIRECTORIO-contactos.md` y
    `web/features/directorio/DIRECTORIO-mi-perfil.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Pantalla de catálogo de tipos de ocupante (`DIRECTORIO-B05`).
  - Pantalla de asignación de ocupantes por unidad (`DIRECTORIO-B07`) — el drawer de detalle de este
    bloque solo **lista** unidades asociadas (lectura), no permite asignar/desasignar desde aquí.
  - Vincular una cuenta de usuario a un contacto existente (no existe ese endpoint, ver
    `DIRECTORIO-B03`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin logueado, contactos existentes | Navegar a `/directorio/contactos` | Tabla con contactos de la org, columna de tipo de vínculo correcta |
| 2 | Admin, con buscador `Perez` | Escribir en el buscador | Tabla filtrada vía `?search=Perez` |
| 3 | Admin, tabla cargada | Click en "Nuevo contacto" → llenar → "Guardar" | POST exitoso, contacto aparece en tabla sin `user_id` |
| 4 | Formulario abierto, nombre vacío | Click en "Guardar" | Error de validación Zod, no se hace POST |
| 5 | Contacto en tabla | Click en fila → drawer de detalle | Muestra datos + unidades asociadas (`GET /contacts/{id}/properties`) |
| 6 | Contacto sin ocupaciones | Click en "Eliminar" → confirmar | DELETE exitoso, desaparece de tabla |
| 7 | Contacto con ocupaciones (API devuelve 409) | Click en "Eliminar" | Toast: "Este contacto tiene unidades asignadas, quítalas primero" |
| 8 | Usuario sin permiso de gestión de contactos | Ver tabla de directorio (si tuviera acceso de lectura) | Columnas de `email`/`telefono` no se renderizan (el API no las envía) |
| 9 | Cualquier usuario autenticado | Navegar a `/perfil` | Formulario precargado con su propio contacto (`GET /me/contact`) |
| 10 | En `/perfil`, modificar `telefono` | Click en "Guardar" | PATCH exitoso a `/me/contact`, toast de éxito |
| 11 | API no disponible (error de red) | Cualquier acción | Toast de error genérico, datos no se pierden |

## Contrato

Este bloque **consume** el contrato `LOCK-DIRECTORIO-02` (producido por `DIRECTORIO-B03`,
`/contacts` + `/me/contact`). No puede pasar a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo los 11 casos de la tabla de criterios, para
      ambas pantallas (`/directorio/contactos` y `/perfil`).
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-DIRECTORIO-02`.
- [ ] `web/features/directorio/DIRECTORIO-contactos.md` y
      `web/features/directorio/DIRECTORIO-mi-perfil.md` creados desde
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> "Mi perfil" y el directorio administrativo comparten este bloque porque ambos consumen el mismo
> lock (`LOCK-DIRECTORIO-02`) y son pantallas pequeñas — separarlas en dos bloques hubiera sido
> partición artificial sin un gate de contrato distinto entre ellas.
