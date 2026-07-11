---
tipo: feature
proyecto: shared
feature: PORTAL_RESIDENTE
actualizado: 2026-07-11
---

# PORTAL_RESIDENTE — Plan de bloques

> Orden de ejecución, dependencias y gates. Este documento es el índice de bloques del feature — el
> estado real de cada uno vive en su propia tarjeta (ver [[../../_system/01_PRINCIPLES#1. Un dato, un dueño]]);
> `_state/BOARD.md` es el rollup global que agrega esto junto con el resto del vault.

## Orden

```
DASHBOARD-B01 (done ✅) ──┐
DASHBOARD-B02 (done ✅) ──┤ (Widget Registry + useActiveCondominium ya existen)
COMUNICACIONES-B01 (ready) ─┤
COBRANZA-B04 (backlog) ─────┼──> PORTAL_RESIDENTE-B01 (web, 2 widgets + resolución de condominio)
DIRECTORIO-B03 (backlog) ───┘
```

Feature de un solo bloque — dos widgets chicos y estrechamente relacionados (ambos son
"agregadores de lectura" sobre el mismo Dashboard), mismo criterio de alcance que
`DASHBOARD-B02` (que combinó 3 widgets de `PROPIEDADES` en un solo bloque).

## Tabla

| ID | Proyecto | Depende de | Estado | Tarjeta |
|---|---|---|---|---|
| PORTAL_RESIDENTE-B01 | web | DASHBOARD-B02, COBRANZA-B04 (lock), COMUNICACIONES-B01 (lock), DIRECTORIO-B03 (lock) | backlog | [[blocks/PORTAL_RESIDENTE-B01-widgets-saldo-avisos]] |

> `PORTAL_RESIDENTE-B01` es el único bloque de esta feature y queda en `backlog` — depende de 3
> locks de features distintas, y solo uno (`COMUNICACIONES-B01`) existe hoy. `COBRANZA-B04`
> (`/me/invoices`) y `DIRECTORIO-B03` (`/me/contact`, `/contacts/{id}/properties`) siguen en
> `backlog` en sus propias features. Este bloque no puede pasar a `ready` hasta que los tres locks
> existan simultáneamente.
>
> Lleva `verificacion_critica: true` por ser el único (y por tanto último) bloque de la feature —
> mismo criterio que `COBRANZA-B11`/`COMUNICACIONES-B02`.
