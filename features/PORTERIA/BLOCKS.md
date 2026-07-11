---
tipo: feature
proyecto: shared
feature: PORTERIA
actualizado: 2026-07-11
---

# PORTERIA — Plan de bloques

> Orden de ejecución, dependencias y gates. Este documento es el índice de bloques del feature — el
> estado real de cada uno vive en su propia tarjeta (ver [[../../_system/01_PRINCIPLES#1. Un dato, un dueño]]);
> `_state/BOARD.md` es el rollup global que agrega esto junto con el resto del vault.

## Orden

```
AUTH-B05 (done ✅) ──┐
PROPIEDADES-B03 (done ✅) ─┤
PROPIEDADES-B04 (done ✅) ─┴──> PORTERIA-B01 (api, migraciones + modelos + CRUD + rol vigilante)
                                        │
                                        ├──lock──> PORTERIA-B02 (web, pantalla Visitantes)
                                        │
                                        └──lock──> PORTERIA-B03 (web, pantalla Correspondencia)
                                                          ▲
DIRECTORIO-B04 (backlog) ──lock──────────────────────────┘

WEB_BOOTSTRAP-B01 (done ✅) ──> PORTERIA-B02, PORTERIA-B03
```

`PORTERIA-B02` (Visitantes) no depende de `DIRECTORIO` — solo necesita el selector de unidad
(`PROPIEDADES`). `PORTERIA-B03` (Correspondencia) sí depende del lock de `DIRECTORIO-B04`
(asignación de ocupantes) para el selector de a quién se entrega el paquete — permanece en
`backlog` hasta que ese lock exista, aunque `PORTERIA-B01` ya esté `done`.

## Tabla

| ID | Proyecto | Depende de | Estado | Tarjeta |
|---|---|---|---|---|
| PORTERIA-B01 | api | AUTH-B05, PROPIEDADES-B03, PROPIEDADES-B04 | ready | [[blocks/PORTERIA-B01-migraciones-modelos-crud-vigilante]] |
| PORTERIA-B02 | web | PORTERIA-B01 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/PORTERIA-B02-pantalla-visitantes]] |
| PORTERIA-B03 | web | PORTERIA-B01 (lock), DIRECTORIO-B04 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/PORTERIA-B03-pantalla-correspondencia]] |

> `PORTERIA-B01` arranca en `ready` — sus tres dependencias (`AUTH-B05`, `PROPIEDADES-B03`,
> `PROPIEDADES-B04`) ya están `done`. **Produce** contrato — al llegar a `done`, se crea el lock
> `LOCK-PORTERIA-01` en `_state/contracts/CONTRACT_LOCKS.md`.
>
> `PORTERIA-B02` puede pasar a `ready` en cuanto `PORTERIA-B01` esté `done` (no necesita esperar a
> `DIRECTORIO`). `PORTERIA-B03` necesita, además, el lock de `DIRECTORIO-B04` — hoy `backlog`, así
> que `PORTERIA-B03` queda bloqueado por una dependencia cross-feature hasta que ese bloque avance.
>
> `PORTERIA-B01` no lleva `verificacion_critica: true` — no introduce auth/pagos/migración
> destructiva sobre datos existentes (crea tablas nuevas, no toca ninguna `SHIPPED`). `PORTERIA-B02`
> y `PORTERIA-B03` sí lo llevan **ambos** — el orden entre ellos no está fijado por dependencias
> (pueden ejecutarse en cualquier orden una vez `PORTERIA-B01` esté `done`), así que no se puede
> designar de antemano cuál cierra la feature. El que efectivamente se ejecute último es el que
> aplica el criterio "features nuevos completos, su último bloque" de
> `_system/05_DEFINITION_OF_DONE.md` §6 (mismo criterio que `COBRANZA-B11`/`COMUNICACIONES-B02`); el
> que se ejecute primero simplemente pasa por `verify-council` sin que sea estrictamente necesario —
> costo aceptado a cambio de no tener que adivinar el orden real de ejecución.
