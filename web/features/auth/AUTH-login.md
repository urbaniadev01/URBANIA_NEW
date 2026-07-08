---
tipo: referencia
proyecto: web
feature: AUTH
actualizado: 2026-07-07
---

# AUTH — Login

**Bloque que la produce:** [[../../../features/AUTH/blocks/AUTH-B06-pantalla-login]]
**Tipo:** Página
**Ruta:** `/login`

## Qué muestra

Formulario de inicio de sesión con campos de email y password. Al enviar credenciales correctas,
redirige al dashboard (`/dashboard`) y almacena el `access_token` en memoria (Zustand, nunca
`localStorage`). Tras un error, muestra un mensaje apropiado según el código de error de la API.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Iniciar sesión | Envío del formulario | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-02]] |

## Estados de la vista

- **Carga:** El botón muestra "Iniciando sesión..." con spinner deshabilitado durante la mutación.
- **Error — INVALID_CREDENTIALS (401):** Toast: "Email o contraseña incorrectos." No distingue
  entre email no registrado y password incorrecta (por diseño de seguridad).
- **Error — ACCOUNT_NOT_ACTIVE (403):** Toast: "Tu cuenta no está activa. Contacta al
  administrador."
- **Error — VALIDATION_ERROR (422):** Toast con el mensaje del servidor.
- **Error — 429 (rate limit):** Toast: "Demasiados intentos. Espera un minuto antes de volver a intentarlo."
  La mutación no reintenta automáticamente (retry: 0 en QueryClient).
- **Error — cliente (campos vacíos / email inválido):** Mensaje inline, submit bloqueado.
  Validación Zod: email requerido + formato, password requerido.
- **Éxito (200):** Redirige a `/dashboard`. El `access_token` se guarda en el store de Zustand
  (memoria), no en `localStorage` ni `sessionStorage`.

## Permisos

Ninguno — el endpoint es público. La pantalla es accesible sin autenticación.
