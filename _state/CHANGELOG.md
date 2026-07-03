---
tipo: estado
proyecto: shared
actualizado: 2026-07-03
---

# CHANGELOG — Historia append-only

> Registro inmutable de cambios cross-project que llegaron a `SHIPPED` (ver
> [[../_system/04_CROSS_PROJECT]] §6). **Nunca se edita una entrada pasada** — solo se agrega al
> final. Si el archivo crece demasiado para leerlo cómodo, se resume en un documento aparte, pero
> este archivo nunca se trunca ni se reescribe (a diferencia del vault anterior, que hizo "resets de
> baseline" que perdieron trazabilidad fuera de git).

## Formato de entrada

```markdown
## SHIP-<NNN> — <título corto> — YYYY-MM-DD
- Feature: [[../features/<FEATURE>/PANORAMA]]
- Bloques incluidos: <FEATURE>-B<NN> (api), <FEATURE>-B<NN> (web)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-<FEATURE>-<NN>]]
- Evidencia: enlace a la sección "Evidencia" de cada tarjeta involucrada
```

## Entradas

_Vacío — el vault arranca en punto 0. La primera entrada se agrega cuando el primer bloque
cross-project de AUTH (API + Web) llegue a `SHIPPED`._
