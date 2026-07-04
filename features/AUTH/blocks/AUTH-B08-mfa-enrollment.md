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

Al detallar este bloque: ningún secreto TOTP se expone por un endpoint de conveniencia, ni en
`local`/`testing` (a diferencia de `AUTH-B01`/`AUTH-B09`, donde el "código" es un token de un solo
uso sin valor fuera de esa ventana). Si hace falta un mecanismo de prueba para cuentas semilla de
demo, se resuelve sembrando un secreto conocido para esas cuentas específicas — el detalle de cómo
derivar el código vigente a partir de ese secreto no es contenido de este vault.
