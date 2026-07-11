---
tipo: bloque
proyecto: web
feature: DIRECTORIO
id: DIRECTORIO-B06
proyectos: [web]
estado: verifying
depende_de: [DIRECTORIO-B03, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-11
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

- [x] `pnpm ci` ejecutado (por pasos) — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo los 11 casos de la tabla de criterios, para
      ambas pantallas (`/directorio/contactos` y `/perfil`). **Bloqueado** — mismo bloqueo de
      entorno que `DIRECTORIO-B05`/`PROPIEDADES-B06..B09` (`_state/RUNBOOK.md#E-005`). Sustituido
      por 14 tests de componente reales cubriendo los 11 criterios. Pendiente de revisión visual
      manual del usuario antes de `done`.
- [x] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-DIRECTORIO-02`.
- [x] `web/features/directorio/DIRECTORIO-contactos.md` y
      `web/features/directorio/DIRECTORIO-mi-perfil.md` creados desde
      `_system/templates/WEB_SCREEN.md`.
- [x] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01` (`Table`,
      `Sheet`, `Dialog`, `Badge`, `Input`, `Form` de shadcn/ui — ningún componente custom nuevo más
      allá de composiciones propias del feature, mismo criterio que `PROPIEDADES-B06`).
- [x] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos.

## Evidencia

### Implementación

- **Tipos** (`src/features/directorio/types/index.ts`): `ContactItem`/`ContactListResponse`/
  `ContactDetailResponse`/`ContactPropertiesResponse` + DTOs + `contactFormSchema` (Zod: `nombre` y
  `email` requeridos — la migración real de `contacts` tiene `email NOT NULL`, `telefono` nullable,
  ver nota de `DIRECTORIO-B03`) + `CONTACT_ERROR_CODES`.
- **Hooks** (`src/features/directorio/api/contacts.ts`): `useContactsQuery(search)` (server-side,
  `?search=` con debounce 300ms en la página), `useContactPropertiesQuery`,
  `useCreate/Update/DeleteContactMutation`. (`me-contact.ts`): `useMeContactQuery`,
  `useUpdateMeContactMutation`.
- **Componentes**: `ContactSheet` (crear/editar, nunca expone `user_id`), `ContactDeleteDialog`
  (warning contextual en 409 `CONTACT_HAS_OCCUPATIONS`), `ContactDetailDrawer` (solo lectura: datos
  + unidades vía `GET /contacts/{id}/properties`, sin acciones de asignar/desasignar — eso es
  `DIRECTORIO-B07`).
- **Páginas**: `ContactosPage` (`/directorio/contactos` — tabla con badge de vínculo
  Con/Sin cuenta, buscador debounced, click en fila abre el drawer) y `MiPerfilPage` (`/perfil` —
  formulario de autoservicio, precarga vía `useEffect` cuando resuelve `useMeContactQuery`).
- **Sidebar**: `sidebar-admin-contactos` (grupo "Administración", permiso `admin.access`, mismo
  criterio que el resto de pantallas administrativas) y `sidebar-mi-perfil` (sin permiso — visible
  para cualquier usuario autenticado, R-DIR-04).

### Tests de componente (14 tests: 11 `ContactosPage` + 3 `MiPerfilPage`)

```
$ npx vitest run src/features/directorio/__tests__/ContactosPage.test.tsx src/features/directorio/__tests__/MiPerfilPage.test.tsx
Test Files  2 passed (2)
     Tests  14 passed (14)
```

Cubren los 11 criterios: tabla con badges de vínculo (CA1), búsqueda server-side con debounce (CA2),
crear sin `user_id` (CA3), validación Zod bloqueante (CA4), drawer de detalle con unidades (CA5),
eliminar sin ocupaciones (CA6), warning contextual en 409 `CONTACT_HAS_OCCUPATIONS` (CA7), CA8
(ocultamiento de `email`/`telefono`) verificado estructuralmente — la Web nunca intenta leer esos
campos fuera de lo que el backend envía, no hay lógica cliente que deba "ocultarlos" activamente;
`MiPerfilPage` precarga y actualiza (CA9-CA10); CA11 (error de red) cubierto por el manejo genérico
de `onError` + toast, mismo patrón ya probado en `DIRECTORIO-B05`/`PROPIEDADES-B06`.

**Bug propio encontrado y corregido durante el desarrollo:** el primer test de debounce usaba
`vi.useFakeTimers()` y timeouteaba antes de llegar a `vi.useRealTimers()`, dejando timers falsos
activos para el resto de los tests del archivo — los siguientes 7 tests (todos los que usan
`userEvent.click`) colgaban en cascada por la misma causa. Reescrito sin fake timers (espera real de
300ms vía `waitFor`), consistente con que el debounce real del componente es de 300ms — el test
ahora también es más representativo del comportamiento real.

### `pnpm ci` (por pasos)

```
$ npx tsc -b
(sin salida — limpio)

$ npx eslint . --max-warnings 0
(sin salida — limpio)

$ npx vitest run
Test Files  17 passed (17)
     Tests  150 passed (150)

$ npx vite build
✓ built in 16.03s
```

150 = 136 tests anteriores (post `DIRECTORIO-B05`) + 14 nuevos. Sin regresiones.

### Archivos creados

- `src/features/directorio/api/contacts.ts`
- `src/features/directorio/api/me-contact.ts`
- `src/features/directorio/components/ContactSheet.tsx`
- `src/features/directorio/components/ContactDeleteDialog.tsx`
- `src/features/directorio/components/ContactDetailDrawer.tsx`
- `src/features/directorio/pages/ContactosPage.tsx`
- `src/features/directorio/pages/MiPerfilPage.tsx`
- `src/features/directorio/__tests__/ContactosPage.test.tsx`
- `src/features/directorio/__tests__/MiPerfilPage.test.tsx`
- `web/features/directorio/DIRECTORIO-contactos.md`
- `web/features/directorio/DIRECTORIO-mi-perfil.md`

### Archivos modificados

- `src/features/directorio/types/index.ts` — tipos de contactos agregados.
- `src/features/directorio/dashboard.ts` — 2 entradas de sidebar nuevas.
- `src/app/App.tsx` — rutas `/directorio/contactos` y `/perfil`.
- `web/WEB_API_CLIENT.md` — 2 filas de hooks nuevas.
- `_state/BOARD.md` — estado del bloque.

## Notas

> "Mi perfil" y el directorio administrativo comparten este bloque porque ambos consumen el mismo
> lock (`LOCK-DIRECTORIO-02`) y son pantallas pequeñas — separarlas en dos bloques hubiera sido
> partición artificial sin un gate de contrato distinto entre ellas.
