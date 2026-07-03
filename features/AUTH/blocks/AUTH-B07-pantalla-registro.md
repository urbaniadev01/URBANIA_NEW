---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B07
proyectos: [web]
estado: backlog
depende_de: [AUTH-B01]
contrato: consume
actualizado: 2026-07-03
---

# AUTH-B07 — Pantalla de registro por invitación

## Objetivo

Pantalla `/register/:token`: lee el token de invitación de la URL, formulario de password, consume
`POST /auth/register`.

## Alcance

**Incluye:**
- Pantalla `/register/:token`.
- Formulario de password + confirmación (Zod + React Hook Form).
- Manejo de errores: `INVITATION_TOKEN_INVALID`, `EMAIL_ALREADY_REGISTERED`, `VALIDATION_ERROR`.
- Tras éxito: redirige a `/login` con un mensaje de "cuenta creada, inicia sesión" (no auto-login,
  consistente con el alcance de `AUTH-B01`).

**No incluye:**
- Envío/creación de invitaciones desde la UI — no existe ese endpoint todavía (ver `AUTH-B01`
  "No incluye").

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Token válido en la URL, password válido | Enviar formulario | `201`, redirige a `/login` con mensaje de éxito |
| 2 | Token inválido/expirado/consumido (`INVITATION_TOKEN_INVALID`) | Cargar la pantalla o enviar el formulario | Mensaje claro de que la invitación no es válida, sin exponer el motivo exacto (no distinguir "expirada" de "consumida" en la UI, para no dar información útil a un atacante) |
| 3 | Email ya registrado (`EMAIL_ALREADY_REGISTERED`) | Enviar formulario | Mensaje que sugiere iniciar sesión en vez de registrarse |
| 4 | Password y confirmación no coinciden | Intentar enviar | Validación de cliente bloquea el submit |

## Contrato

**Consume** `LOCK-AUTH-01` (`POST /auth/register`). No puede pasar a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real (Playwright) de los 4 casos.
- [ ] Tipos de request/response coinciden exactamente con `LOCK-AUTH-01`.

## Evidencia

_Vacío._

## Notas

_Vacío._
