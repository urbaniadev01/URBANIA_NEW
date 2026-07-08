---
tipo: ejemplo
proyecto: api
actualizado: 2026-07-06
---

# EXAMPLE_API_BUILD — Ejemplo comentado de implementación API

> **Agente objetivo:** `api-build`. Leer antes de la primera implementación de una sesión.
> **Feature de referencia:** AUTH (registro por invitación). El código real puede diferir — esto
> ilustra el formato, nivel de detalle, y tipo de evidencia esperados.

## 1. Entrada: lo que recibe el builder

### Tarjeta (resumida)

```
ID: AUTH-B01
Objetivo: Permitir que un usuario se registre solo si posee una invitación válida.
Alcance incluye: endpoint POST /auth/register, validación de token de invitación,
                 creación de User, hash de password con Argon2id.
Alcance NO incluye: login (AUTH-B02), refresh token (AUTH-B03), emails de invitación
                    (el token ya existe en la BD, sembrado por un seeder).
```

### Prompt de delegación (generado por delegate-block)

```
🎯 BLOQUE: AUTH-B01
✅ INCLUYE: POST /auth/register, validar invitation_token, crear User, hash Argon2id
❌ NO INCLUYE: login, refresh, emails, UI, forgot-password
🧪 Criterios:
  - [ ] 201 con token JWT cuando invitation_token es válido
  - [ ] 422 cuando invitation_token falta o es inválido
  - [ ] 409 cuando el email ya está registrado
  - [ ] 422 cuando password no cumple política (mín 8 chars, 1 mayúscula, 1 número)
📁 Archivos esperados: app/Http/Controllers/Auth/RegisterController.php,
  app/Http/Requests/Auth/RegisterRequest.php, app/Actions/Auth/RegisterUser.php,
  tests/Feature/Auth/RegisterTest.php, database/migrations/...add_invitations_table.php
🚫 PROHIBIDO: code/web/, modelos de otros bounded contexts, modificar migraciones existentes
```

## 2. Proceso: qué hace el builder (paso a paso)

### Paso 1 — Leer y confirmar

```
🧠 Razonamiento: La tarjeta está en estado ready. Depende de API_BOOTSTRAP-B01 (done ✅).
El alcance es claro: un endpoint, una acción, tests. No necesito leer el panorama completo
— solo la tarjeta y API_ARCHITECTURE.md para convenciones.
```

### Paso 2 — Crear archivos en orden

1. **Migración** — `create_invitations_table` (si no existe). Campos: `id`, `email`, `token` (unique), `expires_at`, `used_at`, timestamps.
2. **Modelo** — `App\Models\Auth\Invitation` (bounded context Auth). `final readonly`. UUID v7.
3. **Request** — `App\Http\Requests\Auth\RegisterRequest` con reglas: `invitation_token` required|string|exists:invitations,token, `name` required, `email` required|email|unique:users, `password` required|min:8|regex:...
4. **Action** — `App\Actions\Auth\RegisterUser`: recibe DTO, verifica invitation no expirada ni usada, crea User, marca invitation como usada, retorna User.
5. **Controller** — `App\Http\Controllers\Auth\RegisterController`: `__invoke(RegisterRequest $request)`, llama al Action, retorna `UserResource` con 201.
6. **Test** — `tests/Feature/Auth\RegisterTest.php`: 4 tests (camino feliz + 3 casos negativos).

### Paso 3 — Verificar

```
> composer ci
PHPStan: OK (0 errors)
Pint: OK (no changes)
Pest: 4 passed, 0 failed
```

## 3. Salida: evidencia pegada en la tarjeta

```markdown
## Evidencia

### CI
```
> composer ci
  ✔ phpstan: OK (level 9, 0 errors)
  ✔ pint: OK
  ✔ pest: 4 passed (RegisterTest::test_register_with_valid_invitation,
         test_register_fails_with_expired_invitation,
         test_register_fails_with_used_invitation,
         test_register_fails_with_invalid_token)
```

### Criterios de aceptación
| Criterio | Estado | Evidencia |
|---|---|---|
| 201 con invitation_token válido | ✅ | Pest test `test_register_with_valid_invitation` — assertStatus(201), assertJsonStructure({data: {token, user}}) |
| 422 si falta token | ✅ | Pest test `test_register_fails_with_invalid_token` — assertStatus(422), assertJsonValidationErrors(['invitation_token']) |
| 409 email duplicado | ✅ | Pest test `test_register_fails_with_duplicate_email` — assertStatus(409) |
| 422 password débil | ✅ | Pest test `test_register_fails_with_weak_password` — assertStatus(422) |
| Token se marca como usado | ✅ | Pest test — assertDatabaseHas('invitations', ['token' => $token, 'used_at' => notNull]) |
| Password hasheado (nunca plaintext) | ✅ | Pest test — assertNotEquals($plainPassword, $user->password), assertTrue(Hash::check($plainPassword, $user->password)) |

### Contrato (si aplica — este bloque produce)
POST /auth/register — documentado en `api/endpoints/auth/register.md`. Request/Response con ejemplos.
```

## 4. Errores comunes que este ejemplo evita

| Error | Cómo se evita |
|---|---|
| "Asumí que el invitation_token se genera en este bloque" | ❌ El alcance dice claramente "el token ya existe en la BD". No creé un generador de tokens. |
| "Hice el login también, ya que estaba" | ❌ El alcance NO INCLUYE login. Si lo hubiera hecho, habría violado el scope y posiblemente roto AUTH-B02. |
| "No hice tests porque el código es simple" | ❌ El DoD exige tests. Sin tests, el verifier devuelve el bloque a in_progress. |
| "Pegué un resumen en vez de la evidencia real" | ❌ El verifier re-ejecuta composer ci. Si la evidencia es un resumen, no coincide con lo que el verifier ve. |
