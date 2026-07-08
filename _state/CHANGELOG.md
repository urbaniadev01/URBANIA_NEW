---
tipo: estado
proyecto: shared
actualizado: 2026-07-08
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

## SHIP-005 — Rollback de auditoría: AUTH-B01 a B05 a backlog — 2026-07-05
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques revertidos: AUTH-B01, AUTH-B02, AUTH-B03, AUTH-B04, AUTH-B05 (api) — todos a `backlog`
- Motivo: Auditoría del vault reveló que el código de implementación no existe en `code/api/` (solo 2 commits de API_BOOTSTRAP-B01). La documentación se conserva como especificación.
- Bloques web conservados: AUTH-B06, AUTH-B07 (web) — código existe sin commit, en espera de API.
- Locks afectados: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-01]], [[contracts/CONTRACT_LOCKS#LOCK-AUTH-02]], [[contracts/CONTRACT_LOCKS#LOCK-AUTH-03]], [[contracts/CONTRACT_LOCKS#LOCK-AUTH-04]] — conservados como especificación.
- Próximo paso: Re-implementar AUTH-B01 (`POST /auth/register`) desde su tarjeta de especificación.

## SHIP-006 — Reset completo de desarrollo — 2026-07-05
- Feature: Todas
- Motivo: Reinicio de desarrollo desde cero. Código en `code/api/` y `code/web/` eliminado por el usuario.
- Bloques afectados: Todos los bloques vuelven a `backlog` (API_BOOTSTRAP-B01, WEB_BOOTSTRAP-B01, AUTH-B01 a AUTH-B09).
- Documentación de diseño conservada intacta: features, contratos, arquitectura, shared.
- Locks de contrato: Conservados como especificación — código productor pendiente de implementación.
- Próximo paso: Retomar `API_BOOTSTRAP-B01` para crear el esqueleto Laravel desde cero.

## SHIP-007 — MFA Enrollment (API) — 2026-07-07
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques incluidos: AUTH-B08 (api)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-08]], [[contracts/CONTRACT_LOCKS#LOCK-AUTH-02]] (actualizado)
- Evidencia: [[../features/AUTH/blocks/AUTH-B08-mfa-enrollment#Evidencia]]
- Notas: Flujo completo de MFA con TOTP + recovery codes. 5 endpoints nuevos. Modificación no-breaking de login para respuesta `mfa_required`. 74 tests (247 assertions). Verify-council: 4 bloqueantes resueltos post-revisión.

## SHIP-008 — Recuperación de contraseña (API) — 2026-07-07
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques incluidos: AUTH-B09 (api)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-09]]
- Evidencia: [[../features/AUTH/blocks/AUTH-B09-recuperacion-password#Evidencia]]
- Notas: Flujo forgot-password / reset-password. Token SHA-256 hasheado, un solo uso, respuesta anti-enumeration. Endpoint dev en routes/dev.php. Verify-council: aprobado sin bloqueantes.

## SHIP-009 — Feature AUTH completo (SHIPPED) — 2026-07-07
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques incluidos: AUTH-B01 a AUTH-B09 (api: B01-B05, B08-B09; web: B06-B07)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-01]] a [[contracts/CONTRACT_LOCKS#LOCK-AUTH-09]]
- Evidencia: 88 tests, 299 assertions. composer ci: PASS (lint + phpstan nivel 10 + tests)
- Release-council: 🟡 GO CON CONDICIONES. Condiciones: softDeletesTz en role_assignments, throttling en endpoints MFA, Redis gating en dev, documentación desactualizada.
- Notas: Feature AUTH completado según PANORAMA aprobado. MFA y recuperación de contraseña en API solamente — pantallas Web corresponden a fase futura.

## SHIP-010 — Gate cross-project: Fase 2 Web (MFA + Recuperación) — 2026-07-07
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques autorizados (backlog → ready): AUTH-B10, AUTH-B11, AUTH-B12, AUTH-B13 (web)
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-08]], [[contracts/CONTRACT_LOCKS#LOCK-AUTH-09]]
- Productores (done): AUTH-B08 (MFA, 5 endpoints), AUTH-B09 (recuperación, 2 endpoints)
- Consumidores registrados:
  - LOCK-AUTH-08: AUTH-B10 (mfa-verify-web), AUTH-B11 (mfa-enroll-web)
  - LOCK-AUTH-09: AUTH-B12 (forgot-password-web), AUTH-B13 (reset-password-web)
- Gate: ✅ API productora done + locks vigentes + consumidores registrados. Los 4 bloques web están autorizados para pasar de `backlog` a `ready`.

## SHIP-011 — Fase 2 Web: MFA + Recuperación (AUTH-B10–B13) — 2026-07-08
- Feature: [[../features/AUTH/PANORAMA]]
- Bloques incluidos: AUTH-B10 (mfa-verify-web), AUTH-B11 (mfa-enroll-web), AUTH-B12 (forgot-password-web) — web
- Bloque pendiente: AUTH-B13 (reset-password-web) en `ready`
- Locks de contrato: [[contracts/CONTRACT_LOCKS#LOCK-AUTH-08]], [[contracts/CONTRACT_LOCKS#LOCK-AUTH-09]]
- Evidencia: [[../features/AUTH/blocks/AUTH-B10-mfa-verify-web#Evidencia]], [[../features/AUTH/blocks/AUTH-B11-mfa-enroll-web#Evidencia]], [[../features/AUTH/blocks/AUTH-B12-forgot-password-web#Evidencia]]
- Notas: Pantallas Web para MFA (verify + enroll) y recuperación de contraseña (forgot password). Correcciones post-auditoría 2026-07-08: ruta /dashboard, manejo mfa_required en login, destinos post-auth unificados.
