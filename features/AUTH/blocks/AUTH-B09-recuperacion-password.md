---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B09
proyectos: [api]
estado: backlog
depende_de: [AUTH-B02]
contrato: null
actualizado: 2026-07-03
---

# AUTH-B09 — Recuperación de contraseña (sin detallar)

## Objetivo

Flujo `forgot-password` / `reset-password`. Se detalla — alcance, criterios de aceptación, DoD —
cuando le toque el turno, siguiendo la misma plantilla que `AUTH-B01`–`AUTH-B05`.

## Notas

Tarjeta intencionalmente incompleta — `estado: backlog` refleja que no está lista para ejecutarse,
no solo que espera dependencia. No mover a `ready` sin completar Alcance, Criterios de aceptación y
Definition of Done primero.

Al detallar este bloque: agregar el mismo patrón de endpoint dev que `AUTH-B01` (ver
`api/API_ARCHITECTURE.md` §9) para el token de reset — ej. `GET /dev/password-resets/last?email=...`,
bajo la misma convención `routes/dev.php` (solo `local`/`testing`, fuera de `/api/v1/`).
