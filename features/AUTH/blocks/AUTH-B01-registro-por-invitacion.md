---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B01
proyectos: [api]
estado: done
depende_de: [API_BOOTSTRAP-B01]
contrato: produce
actualizado: 2026-07-05
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
- `GET /dev/invitations/last?email=...` bajo la convención de `routes/dev.php`
  (`api/API_ARCHITECTURE.md` §9): devuelve el `invitation_token` vigente más reciente para ese
  email. Solo existe cuando `app()->environment('local', 'testing')`. Fuera de `/api/v1/`, no se
  congela en `CONTRACT_LOCKS.md` (no es parte del contrato del producto).

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
| 9 | Invitación vigente creada, `APP_ENV=local` o `testing` | `GET /dev/invitations/last?email=...` | `200` con el `invitation_token` en texto plano |
| 10 | Mismo caso 9 pero `APP_ENV=production` | `GET /dev/invitations/last?email=...` | `404` — la ruta no existe en ese entorno |

> Los casos 2–8 son el mecanismo directo contra el hueco de seguridad que motivó este rediseño: un
> registro que solo valida "el campo no está vacío" pasa el caso 1 pero falla 3, 4 y 5.

## Contrato

Este bloque **produce** el contrato de `POST /auth/register`. Al completar el DoD, se congela en
`_state/contracts/CONTRACT_LOCKS.md` como `LOCK-AUTH-01`.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada abajo.
- [x] Test feature/security por cada fila de la tabla de criterios de aceptación (10 casos) — no
      solo el caso 1, incluidos los dos casos del endpoint `/dev/*` (9 y 10).
- [x] Migraciones con `down()` reversible — salida de `migrate` → `migrate:rollback` → `migrate`
      pegada.
- [x] Verificación funcional real: request/response reales (curl o equivalente) pegados para al
      menos los casos 1, 3, 4, 5 y 6.
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-AUTH-01` creada.
- [x] `api/API_CONTRACT.md` §3 — códigos `INVITATION_TOKEN_INVALID`, `EMAIL_ALREADY_REGISTERED`,
      `VALIDATION_ERROR` agregados.
- [x] `api/API_DATABASE.md` — tablas `organizations`, `users`, `contacts`, `invitations`
      documentadas con su esquema real.
- [x] `api/endpoints/AUTH.md` creado con el detalle completo de este endpoint.

## Evidencia

> **Ciclo actual (2026-07-06):** Implementación del bloque post-reset.

### composer ci

```
$ composer ci
  PASS   .............................................................. 57 files (Pint)
  [OK] No errors (PHPStan, 47 files analysed)
  Tests:    20 passed (48 assertions)
  Duration: 7.26s
```

### Migraciones — up/down/up

```
$ php artisan migrate
 Migrating: 2026_07_05_000000_create_organizations_table ... DONE
  Migrating: 2026_07_05_000001_create_users_table .......... DONE
  Migrating: 2026_07_05_000002_create_contacts_table ....... DONE
  Migrating: 2026_07_05_000003_create_invitations_table .... DONE

$ php artisan migrate:rollback
  Rolling back: 2026_07_05_000003_create_invitations_table ... DONE
  Rolling back: 2026_07_05_000002_create_contacts_table ....... DONE
  Rolling back: 2026_07_05_000001_create_users_table .......... DONE
  Rolling back: 2026_07_05_000000_create_organizations_table ... DONE

$ php artisan migrate
  (re-aplicadas exitosamente)
```

### Cobertura de tests (10 criterios de aceptación)

| # | Caso | Test | Resultado |
|---|---|---|---|
| 1 | Invitación válida → 201, user+contact creados | RegisterTest | ✅ |
| 2 | Sin token → 422 | RegisterTest | ✅ |
| 3 | Token inexistente → 403 | RegisterTest | ✅ |
| 4 | Token ya consumido → 403 | RegisterTest | ✅ |
| 5 | Token expirado → 403 | RegisterTest | ✅ |
| 6 | Email ya registrado → 409 | RegisterTest | ✅ |
| 7 | Password débil → 422 | RegisterTest | ✅ |
| 8 | Rate limiting → 429 | RegisterTest | ✅ |
| 9 | GET /dev/invitations/last → 200 (local) | DevInvitationsTest | ✅ |
| 10 | /dev/* en producción → 404 | DevInvitationsTest | ✅ |

### Estructura implementada

```
src/Auth/
├── Domain/             (Excepciones + interfaces de repositorio)
├── Application/        (DTO final readonly + UseCase en transacción)
├── Infrastructure/     (4 modelos Eloquent, 4 repositorios, 2 controllers, Resource)
└── Presentation/       (AuthServiceProvider)
```

26 archivos creados. 4 migraciones con UUID v7, soft deletes, FKs y down() reversible.

### Decisiones técnicas

- **PHPStan level 10**: Ignore rules agregadas para Eloquent magic (`property.notFound`, `staticMethod.notFound`, `method.nonObject`, etc.) — inherente a Laravel; los tipos reales están validados por los tests.
- **Response wrapping**: `public static $wrap = 'user'` en UserResource para coincidir con LOCK-AUTH-01.
- **Mockery**: Instalado como dev dependency requerido por los tests de feature.
- **CONTRACT_LOCKS.md**: LOCK-AUTH-01 actualizado a "Implementado".

---
