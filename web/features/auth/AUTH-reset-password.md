---
tipo: referencia
proyecto: web
feature: AUTH
actualizado: 2026-07-07
---

# AUTH — Nueva contraseña (reset)

**Bloque que la produce:** [[../../../features/AUTH/blocks/AUTH-B13-reset-password-web]]
**Tipo:** Página
**Ruta:** `/reset-password?token=...&email=...`

## Qué muestra

Pantalla pública que recibe `token` (64 caracteres hex) y `email` por query params. Muestra un formulario de nueva contraseña + confirmación con checklist en tiempo real de los 4 requisitos de política de contraseña. Al enviar exitosamente, redirige a `/login` con mensaje de éxito. Si el token es inválido o expiró, reemplaza el formulario por un mensaje de error con enlace para solicitar uno nuevo.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Actualizar contraseña | Envío del formulario → redirige a `/login` | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-09]] |

## Estados de la vista

- **Params ausentes (token o email):** card "Enlace inválido o incompleto. Solicita un nuevo enlace de recuperación." + botón "Ir a recuperación de contraseña" → `/forgot-password`. Sin formulario.
- **Formulario activo:** título "Nueva contraseña", subtítulo "Restableciendo contraseña para {email}", campos de nueva contraseña y confirmación, checklist en tiempo real con 4 requisitos (ícono check verde / X roja), botón "Actualizar contraseña", enlace "Volver a inicio de sesión".
- **Carga:** botón deshabilitado con spinner `Loader2` ("Actualizando contraseña..."), campos deshabilitados.
- **Éxito (200):** toast "Contraseña actualizada exitosamente. Inicia sesión con tu nueva contraseña." + redirección a `/login`.
- **Error RESET_TOKEN_INVALID (422):** formulario reemplazado por card "Enlace inválido" + mensaje "Este enlace ya no es válido. Solicita uno nuevo." + botón "Solicitar un nuevo enlace" → `/forgot-password`.
- **Error RESET_TOKEN_EXPIRED (422):** formulario reemplazado por card "Enlace inválido" + mensaje "Este enlace expiró (válido por 60 minutos). Solicita uno nuevo." + botón "Solicitar un nuevo enlace" → `/forgot-password`.
- **Error VALIDATION_ERROR (422):** toast con mensaje del servidor (defensa).
- **Error TOO_MANY_REQUESTS (429):** toast "Demasiados intentos. Espera 15 minutos e inténtalo de nuevo." — formulario sigue visible.

## Permisos

Ninguno — pantalla pública, como `/login`, `/register` y `/forgot-password`.

## Checklist de requisitos

La política de contraseña es idéntica a la de registro (`AUTH-B07`, `passwordSchema` compartido en `auth.types.ts`):

1. Al menos 8 caracteres
2. Al menos una mayúscula
3. Al menos una minúscula
4. Al menos un número

Los 4 requisitos se muestran debajo del campo de contraseña con ícono check (verde) o X (roja) que se actualiza en tiempo real mientras el usuario escribe (vía `watch` de React Hook Form).
