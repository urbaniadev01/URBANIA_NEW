---
tipo: estado
proyecto: shared
actualizado: 2026-07-05
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

## SHIP-001 — Login cross-project (API + Web) — 2026-07-04
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques incluidos: AUTH-B02 (api), AUTH-B06 (web)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-02]]
- Evidencia: [[../features/AUTH/blocks/AUTH-B02-login#Evidencia]], [[../features/AUTH/blocks/AUTH-B06-pantalla-login#Evidencia]]

## SHIP-002 — Registro cross-project (API + Web) — 2026-07-04
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques incluidos: AUTH-B01 (api), AUTH-B07 (web)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-01]]
- Evidencia: [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion#Evidencia]], [[../features/AUTH/blocks/AUTH-B07-pantalla-registro#Evidencia]]

## SHIP-003 — Refresh token (API) — 2026-07-05
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques incluidos: AUTH-B03 (api)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-03]]
- Evidencia: [[../features/AUTH/blocks/AUTH-B03-refresh-token#Evidencia]]

## SHIP-004 — Logout + RBAC middleware (API) — 2026-07-05
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques incluidos: AUTH-B04 (api), AUTH-B05 (api)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-04]]
- Evidencia: [[../features/AUTH/blocks/AUTH-B04-logout#Evidencia]], [[../features/AUTH/blocks/AUTH-B05-rbac-middleware#Evidencia]]
