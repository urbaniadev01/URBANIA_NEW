---
tipo: referencia
proyecto: web
feature: <FEATURE>
actualizado: YYYY-MM-DD
---

> Plantilla — copiar a `web/features/<feature-slug>/<FEATURE>-<pantalla-slug>.md`. Una pantalla por
> archivo. El detalle de componentes reusables vive en `web/WEB_ARCHITECTURE.md`, no aquí.

# <FEATURE> — <Nombre de la pantalla>

**Bloque que la produce:** [[../../../features/<FEATURE>/blocks/<FEATURE>-B<NN>-...]]
**Tipo:** Página / Modal / Drawer / Sheet / Inline
**Ruta:** `/<ruta>`

## Qué muestra

Descripción funcional breve.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| <acción> | <qué pasa en la UI> | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-<FEATURE>-<NN>]] |

## Estados de la vista

- Carga
- Vacío
- Error (por cada error del endpoint listado en `api/endpoints/<FEATURE>.md`, cómo se muestra)
- Éxito

## Permisos

Qué rol/permiso condiciona que esta pantalla o alguna de sus acciones sea visible.
