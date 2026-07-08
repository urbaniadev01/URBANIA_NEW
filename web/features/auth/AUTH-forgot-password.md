---
tipo: referencia
proyecto: web
feature: AUTH
actualizado: 2026-07-07
---

# AUTH — Recuperación de contraseña (solicitud)

**Bloque que la produce:** [[../../../features/AUTH/blocks/AUTH-B12-forgot-password-web]]
**Tipo:** Página
**Ruta:** `/forgot-password`

## Qué muestra

Pantalla pública con un formulario de un solo campo (email) para solicitar un enlace de recuperación de contraseña. La API responde siempre `200` con un mensaje genérico (anti-enumeración), y la UI replica ese comportamiento — mismo mensaje y estado visual para email existente y no existente. Incluye enlace para volver a `/login`.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Solicitar recuperación | Envío del formulario → mensaje genérico, formulario oculto | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-09]] |

## Estados de la vista

- **Inicial:** formulario con campo email + botón "Enviar enlace de recuperación" + enlace "Volver a inicio de sesión".
- **Carga:** botón deshabilitado con spinner `Loader2`, campo email deshabilitado.
- **Éxito (200):** formulario reemplazado por mensaje "Si el email está registrado, recibirás un enlace de recuperación. Revisa tu bandeja de entrada y spam." + enlace "Volver a inicio de sesión". Mismo mensaje para email existente y no existente.
- **Error 429 (TOO_MANY_REQUESTS):** toast "Demasiadas solicitudes. Espera e inténtalo de nuevo más tarde." — formulario sigue visible.
- **Error de red / genérico:** toast con mensaje genérico.

## Permisos

Ninguno — pantalla pública, como `/login` y `/register`.
