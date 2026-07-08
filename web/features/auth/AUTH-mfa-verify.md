---
tipo: referencia
proyecto: web
feature: AUTH
actualizado: 2026-07-07
---

# AUTH — Verificación MFA

**Bloque que la produce:** [[../../../features/AUTH/blocks/AUTH-B10-mfa-verify-web]]
**Tipo:** Página
**Ruta:** `/mfa/verify`

## Qué muestra

Pantalla que recibe al usuario tras un login donde la API respondió `mfa_required: true`. Muestra un
formulario con un único campo para código TOTP (6 dígitos) o recovery code (10 caracteres, formato
`XXXXX-XXXXX`). El `mfa_token` ya reside en cookie httpOnly — la pantalla solo envía el código, sin
manipular la cookie.

Si el `mfa_token` expiró (`MFA_TOKEN_INVALID`, 401), la pantalla oculta el formulario y muestra un
mensaje con enlace a `/login`. No hay reintento automático.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Verificar código | POST al endpoint, guarda `access_token` en Zustand, redirige a `/` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]] |

## Estados de la vista

- **Carga:** Botón deshabilitado + spinner durante el submit.
- **Éxito:** Redirige al dashboard `/`.
- **Error `MFA_TOKEN_INVALID` (401):** Oculta el formulario, muestra mensaje "Tu sesión de
  verificación expiró. Vuelve a iniciar sesión." con botón/enlace a `/login`.
- **Error `MFA_CODE_INVALID` (422):** Toast "Código inválido. Intenta de nuevo.", campo limpio.
- **Error `MFA_RECOVERY_CODE_USED` (422):** Toast "Este código de respaldo ya fue utilizado."
- **Error `TOO_MANY_REQUESTS` (429):** Toast "Demasiados intentos. Espera un minuto e inténtalo de
  nuevo.", botón deshabilitado por 60 segundos.
- **Validación client-side:** Campo vacío → "El código es obligatorio." Formato inválido → "Ingresa
  un código TOTP de 6 dígitos o un código de respaldo (formato XXXXX-XXXXX)."

## Permisos

Ninguno — esta pantalla es pública (el `mfa_token` en cookie es la credencial, no el `access_token`
de sesión).
