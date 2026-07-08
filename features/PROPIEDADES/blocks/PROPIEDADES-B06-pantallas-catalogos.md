---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B06
proyectos: [web]
estado: backlog
depende_de: [PROPIEDADES-B02, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-06
---

# PROPIEDADES-B06 — Pantallas de catálogos (TiposPropiedad, EstadosPropiedad)

## Objetivo

Construir las dos pantallas de administración de catálogos en la web: `TiposPropiedad` y
`EstadosPropiedad`. Cada pantalla muestra una tabla con acciones CRUD (crear, editar, eliminar)
usando diálogos, e integra con los endpoints de `LOCK-PROPIEDADES-01`.

## Alcance

- **Incluye:**
  - Página `TiposPropiedad` (`/catalogos/tipos-propiedad`): tabla con columnas (nombre,
    descripción, origen — sistema/personalizado), botón "Nuevo tipo", acciones de editar/eliminar
    por fila.
  - Página `EstadosPropiedad` (`/catalogos/estados-propiedad`): misma estructura que
    TiposPropiedad.
  - Diálogo de crear/editar (Sheet o Dialog de shadcn/ui según `WEB_VISUAL_STANDARDS`) con campos
    `nombre` (requerido) y `descripcion` (opcional).
  - Diálogo de confirmación antes de eliminar, con mensaje contextual ("Este tipo está en uso por
    X propiedades" si el endpoint devuelve 409).
  - Indicador visual de catálogos del sistema (no editables, no eliminables) — badge "Sistema" o
    similar.
  - Integración con API: hooks/clients para consumir `LOCK-PROPIEDADES-01` (`GET /property-types`,
    `POST /property-types`, `PATCH /property-types/{id}`, `DELETE /property-types/{id}`, y sus
    equivalentes para `property-statuses`).
  - Validación Zod en formularios (nombre requerido, longitud mínima/máxima).
  - Manejo de errores del API con toast notifications (422, 403, 409).
  - Documentación de pantalla en `web/features/propiedades/PROPIEDADES-tipos-estados.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Pantallas de condominios (B07), unidades (B08), coeficientes (B09).
  - Pantalla de login/registro (AUTH).
  - Menú de navegación o sidebar — se asume que existe del bootstrap de web o se agrega en este
    bloque como ruta simple.
  - Catálogos de otras features (document_types, etc.).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin logueado, API con tipos del sistema | Navegar a `/catalogos/tipos-propiedad` | Tabla con 5 tipos del sistema + los personalizados de la org, badge "Sistema" en los del sistema |
| 2 | Admin, tabla cargada | Click en "Nuevo tipo" | Sheet/Dialog se abre con formulario vacío |
| 3 | Formulario abierto, nombre válido | Click en "Guardar" | POST exitoso, tipo aparece en tabla, toast de éxito |
| 4 | Formulario abierto, nombre vacío | Click en "Guardar" | Error de validación Zod (campo requerido), no se hace POST |
| 5 | Tipo de sistema en tabla | Ver fila | Sin botones de editar/eliminar (o deshabilitados) |
| 6 | Tipo personalizado en tabla | Click en "Editar" | Sheet/Dialog con datos precargados |
| 7 | Edición abierta, datos modificados | Click en "Guardar" | PATCH exitoso, tabla actualizada |
| 8 | Tipo personalizado sin uso | Click en "Eliminar" → confirmar | DELETE exitoso, tipo desaparece de tabla |
| 9 | Tipo en uso por propiedades (API devuelve 409) | Click en "Eliminar" | Toast de error: "No se puede eliminar: está en uso por X propiedades" |
| 10 | API no disponible (error de red) | Cualquier acción | Toast de error genérico, datos no se pierden |
| 11 | Mismo flujo completo para EstadosPropiedad | Navegar a `/catalogos/estados-propiedad` | Mismo comportamiento que tipos (criterios 1–10) |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-01` (producido por `PROPIEDADES-B02`). No
puede pasar a `ready` sin ese lock vigente. La integración debe respetar exactamente los
request/response definidos en el contrato congelado.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright o equivalente) recorriendo los flujos de ambas pantallas:
      crear, editar, eliminar (éxito), eliminar (409 en uso), catálogo de sistema (sin acciones).
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-PROPIEDADES-01`.
- [ ] `web/features/propiedades/PROPIEDADES-tipos-estados.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      catálogos.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Los catálogos del sistema (`organization_id = NULL`) no son editables ni eliminables. El badge
> "Sistema" se determina por el campo `organization_id` que viene en la respuesta del API. Si el API
> no expone ese campo en el listado, este bloque necesita que B02 lo incluya en el Resource.
