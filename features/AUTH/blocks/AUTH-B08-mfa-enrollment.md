---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B08
proyectos: [api]
estado: backlog
depende_de: [AUTH-B05]
contrato: null
actualizado: 2026-07-03
---

# AUTH-B08 — MFA enrollment (sin detallar)

## Objetivo

Enrolamiento de autenticación multifactor (TOTP + códigos de respaldo, ver
[[../../../shared/GLOSSARY]] "MFA"). Se detalla — alcance, criterios de aceptación, DoD — cuando le
toque el turno, siguiendo la misma plantilla que `AUTH-B01`–`AUTH-B05`.

## Notas

Tarjeta intencionalmente incompleta — `estado: backlog` refleja que no está lista para ejecutarse,
no solo que espera dependencia. No mover a `ready` sin completar Alcance, Criterios de aceptación y
Definition of Done primero.
