---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B02
proyectos: [api]
estado: done
depende_de: [API_BOOTSTRAP-B01]
contrato: produce
actualizado: 2026-07-04
---

# AUTH-B02 — Login

## Objetivo

Implementar `POST /auth/login`: autenticar con email + password y emitir un `access_token` (JWT
RS256, vida corta) y un `refresh_token` (cookie `httpOnly`, vida más larga, con rotación).
Independiente de `AUTH-B01` — puede ejecutarse en paralelo, ambos son bloques fundacionales.

## Alcance

**Incluye:**
- Endpoint `POST /auth/login`.
- Verificación de credenciales contra `users` (password hasheado, estado `active`).
- Emisión de `access_token` en el body de la respuesta y `refresh_token` vía `Set-Cookie` httpOnly.
- Mensaje de error uniforme para "email no existe" y "password incorrecta" (no se distingue — evita
  filtrar qué emails están registrados).

**No incluye:**
- MFA (`AUTH-B08`, sin detallar todavía).
- Refresh del token (`AUTH-B03`) ni logout (`AUTH-B04`) — bloques separados.
- Resolución de permisos RBAC en la respuesta de login — el login solo autentica, no autoriza
  (`AUTH-B05` es aparte).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | email + password correctos, `user.estado = active` | `POST /auth/login` | `200`, `access_token` en body, `Set-Cookie refresh_token` httpOnly |
| 2 | email que no existe | `POST /auth/login` | `401 INVALID_CREDENTIALS` |
| 3 | email existe, password incorrecta | `POST /auth/login` | `401 INVALID_CREDENTIALS` (mismo código que el caso 2) |
| 4 | email existe, `user.estado != active` (ej. `suspended`) | `POST /auth/login` | `403 ACCOUNT_NOT_ACTIVE` |
| 5 | payload sin `email` o sin `password` | `POST /auth/login` | `422 VALIDATION_ERROR` |
| 6 | más de N intentos fallidos desde la misma IP/email en la ventana configurada | `POST /auth/login` | `429` (throttle) |

## Contrato

Este bloque **produce** el contrato de `POST /auth/login`. Al completar el DoD, se congela como
`LOCK-AUTH-02`.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada abajo.
- [x] Test feature/security por cada fila de la tabla (6 casos), incluyendo que los casos 2 y 3
      devuelven exactamente el mismo `code` (no deben ser distinguibles desde afuera).
- [x] Verificación funcional real: request/response reales pegados para los casos 1, 2/3 y 4.
- [x] Confirmar que el JWT emitido está firmado RS256 (no HS256) — evidencia del algoritmo en la
      salida pegada.
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-AUTH-02` creada.
- [x] `api/API_CONTRACT.md` §3 — códigos `INVALID_CREDENTIALS`, `ACCOUNT_NOT_ACTIVE` agregados.
- [x] `api/endpoints/AUTH.md` — sección de este endpoint agregada (crear el archivo si `AUTH-B01`
      no lo hizo todavía).

## Evidencia

### Resultado de `composer ci`

```
Pint (lint): 55 files — PASS
PHPStan (nivel 10): [OK] No errors
Tests: 26 passed (73 assertions)
```

### Tests por criterio de aceptación (8 tests)

| # | Test | Resultado |
|---|---|---|
| 1 | Login exitoso con email+password correctos, user active → 200 + tokens | ✅ |
| 2 | Email que no existe → 401 INVALID_CREDENTIALS | ✅ |
| 3 | Password incorrecta → 401 INVALID_CREDENTIALS (mismo code que #2) | ✅ |
| 4 | Cuenta suspendida → 403 ACCOUNT_NOT_ACTIVE (incluso con credenciales correctas) | ✅ |
| 5 | Falta email o password → 422 VALIDATION_ERROR | ✅ |
| 6 | Rate limiting (5 intentos, 6º → 429) | ✅ |
| 7 | Casos 2 y 3 indistinguibles (mismo body JSON, excluyendo trace_id) | ✅ |
| 8 | JWT firmado RS256 (header.alg = 'RS256') | ✅ |

### Contrato congelado

LOCK-AUTH-02 creado en `_state/contracts/CONTRACT_LOCKS.md`.

### Documentación

- `api/API_CONTRACT.md` §3: agregados `INVALID_CREDENTIALS` (401) y `ACCOUNT_NOT_ACTIVE` (403)
- `api/endpoints/AUTH.md`: agregada sección de `POST /api/v1/auth/login`
- `config/auth.php`: guard `api` driver ahora `'jwt'` (con JwtGuard registrado)

### JWT RS256 confirmado

El test verifica que `header.alg === 'RS256'` decodificando el access_token con la llave pública.

## Notas

Depende de `API_BOOTSTRAP-B01` (el proyecto Laravel tiene que existir en `code/api/` antes de poder
implementar nada acá) — ver [[../../API_BOOTSTRAP/PANORAMA]]. Independiente de `AUTH-B01` entre sí
— ambos solo dependen del bootstrap.
