---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B01
proyectos: [api]
estado: backlog
depende_de: [API_BOOTSTRAP-B01]
contrato: produce
actualizado: 2026-07-03
---

# AUTH-B01 — Registro por invitación

## Objetivo

Implementar `POST /auth/register`: crear una cuenta de usuario nueva **solo** cuando existe una
invitación válida, vigente y no consumida. Es el primer bloque del vault — establece las tablas
fundacionales de identidad (`organizations`, `users`, `contacts`, `invitations`).

## Alcance

**Incluye:**
- Migraciones: `organizations`, `users`, `contacts`, `invitations` (columnas mínimas para este
  bloque — ver §Contrato).
- Endpoint `POST /auth/register`.
- Validación de `invitation_token` **contra la tabla `invitations`** (existencia, estado `vigente`,
  no expirada) — nunca solo "campo no vacío".
- Creación de `user` (estado `active`, password hasheado) + `contact` asociado
  (`contacts.user_id`), en la misma organización que la invitación, dentro de una única transacción.
- Marcar la invitación consumida (`estado: consumida`) al completar el registro.

**No incluye (explícitamente fuera de este bloque):**
- Endpoint para *crear* invitaciones — no existe todavía en este vault (pertenece a una feature
  futura de gestión de usuarios). Para probar este bloque, la invitación se inserta directamente en
  base de datos vía factory de test.
- Asignación de rol/permiso al usuario nuevo — RBAC llega en `AUTH-B05`.
- Vínculo a una unidad/propiedad (`property_occupants`) — depende de la feature Propiedades, que
  todavía no tiene panorama aprobado.
- Login automático tras el registro — el usuario inicia sesión en un paso aparte (`AUTH-B02`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Invitación `vigente`, no expirada, no consumida + password válido | `POST /auth/register` | `201`, `user` y `contact` creados, invitación pasa a `consumida` |
| 2 | Sin `invitation_token` en el body | `POST /auth/register` | `422 VALIDATION_ERROR` |
| 3 | `invitation_token` que no existe en la tabla `invitations` | `POST /auth/register` | `403 INVITATION_TOKEN_INVALID` |
| 4 | `invitation_token` ya consumido previamente | `POST /auth/register` | `403 INVITATION_TOKEN_INVALID` |
| 5 | `invitation_token` con `expira_en` en el pasado | `POST /auth/register` | `403 INVITATION_TOKEN_INVALID` |
| 6 | Email de la invitación ya asociado a un `user` existente | `POST /auth/register` | `409 EMAIL_ALREADY_REGISTERED` |
| 7 | Password que no cumple la política mínima (largo, complejidad) | `POST /auth/register` | `422 VALIDATION_ERROR` |
| 8 | Más de N intentos de registro desde la misma IP en la ventana configurada | `POST /auth/register` | `429` (throttle) |

> Los casos 2–8 son el mecanismo directo contra el hueco de seguridad que motivó este rediseño: un
> registro que solo valida "el campo no está vacío" pasa el caso 1 pero falla 3, 4 y 5.

## Contrato

Este bloque **produce** el contrato de `POST /auth/register`. Al completar el DoD, se congela en
`_state/contracts/CONTRACT_LOCKS.md` como `LOCK-AUTH-01`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada abajo.
- [ ] Test feature/security por cada fila de la tabla de criterios de aceptación (8 casos) — no solo
      el caso 1.
- [ ] Migraciones con `down()` reversible — salida de `migrate` → `migrate:rollback` → `migrate`
      pegada.
- [ ] Verificación funcional real: request/response reales (curl o equivalente) pegados para al
      menos los casos 1, 3, 4, 5 y 6.
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-AUTH-01` creada.
- [ ] `api/API_CONTRACT.md` §3 — códigos `INVITATION_TOKEN_INVALID`, `EMAIL_ALREADY_REGISTERED`,
      `VALIDATION_ERROR` agregados.
- [ ] `api/API_DATABASE.md` — tablas `organizations`, `users`, `contacts`, `invitations`
      documentadas con su esquema real.
- [ ] `api/endpoints/AUTH.md` creado con el detalle completo de este endpoint.

## Evidencia

_Vacío — se completa al ejecutar este bloque._

## Notas

Depende de `API_BOOTSTRAP-B01` (el proyecto Laravel tiene que existir en `code/api/` antes de poder
implementar nada acá) — ver [[../../API_BOOTSTRAP/PANORAMA]].
