---
tipo: feature
proyecto: shared
feature: DASHBOARD
actualizado: 2026-07-09
---

# DASHBOARD — Plan de bloques

> Orden de ejecución, dependencias y gates. Este documento es el índice de bloques del feature — el
> estado real de cada uno vive en su propia tarjeta (ver [[../../_system/01_PRINCIPLES#1. Un dato, un dueño]]);
> `_state/BOARD.md` es el rollup global que agrega esto junto con el resto del vault.
>
> Feature puramente de frontend para V1: cero endpoints nuevos, cero tablas nuevas. El dashboard
> compone widgets de otras features vía un Widget Registry en TypeScript (ver [[PANORAMA#7. Arquitectura de extensibilidad — Widget Registry]]).

## Orden

```
WEB_BOOTSTRAP-B01 (done ✅)
    └──> DASHBOARD-B01 (web, widget registry + core infrastructure)
              ├──> DASHBOARD-B02 (web, PROPIEDADES widgets)
              └──> DASHBOARD-B03 (web, core widgets + placeholders)

PROPIEDADES-B03 ──lock──┐
PROPIEDADES-B04 ──lock──┼──> DASHBOARD-B02
PROPIEDADES-B05 ──lock──┘
```

> B02 consume 3 contratos de PROPIEDADES (todos `done` con locks vigentes). B01 y B03 son
> auto-contenidos — no consumen contratos de API externos al dashboard.

## Tabla

| ID | Proyecto | Depende de | Estado | Tarjeta |
|---|---|---|---|---|
| DASHBOARD-B01 | web | WEB_BOOTSTRAP-B01 | ready | [[blocks/DASHBOARD-B01-widget-registry-core]] |
| DASHBOARD-B02 | web | DASHBOARD-B01, PROPIEDADES-B03 (lock), PROPIEDADES-B04 (lock), PROPIEDADES-B05 (lock) | backlog | [[blocks/DASHBOARD-B02-propiedades-widgets]] |
| DASHBOARD-B03 | web | DASHBOARD-B01 | backlog | [[blocks/DASHBOARD-B03-core-widgets-placeholders]] |

> Solo `DASHBOARD-B01` arranca en `ready` — depende de `WEB_BOOTSTRAP-B01` (done). B02 y B03
> requieren que B01 esté `done` antes de poder pasar a `ready`. B02 además requiere que los 3
> contratos de PROPIEDADES estén vigentes (lo están: LOCK-PROPIEDADES-02, -03, -04).

## Notas de arquitectura

- **B01 produce el Widget Registry** — todos los bloques posteriores (B02, B03, y cualquier feature
  futura) consumen este registro vía `registerWidget()` y `registerSidebarItem()`.
- **B02 es el primer consumidor externo** — valida el patrón zero-touch: una línea de import en
  `bootstrap.ts` y el feature PROPIEDADES aporta 3 widgets sin tocar el core del dashboard.
- **B03 cierra el MVP** — widgets core (Welcome, QuickLinks) + placeholders de features no SHIPPED
  (DIRECTORIO, COBRANZA), visibles solo para staff según R-DASH-03.
- **Extensibilidad futura:** Agregar un feature nuevo al dashboard requiere exactamente UNA línea de
  import en `bootstrap.ts` + un side-effect module en el feature nuevo. Ningún archivo del core del
  dashboard se modifica (ver [[PANORAMA#7.4 Bootstrap — punto unico de importacion]]).
