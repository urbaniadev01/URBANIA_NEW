---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B13
proyectos: [web]
estado: ready
depende_de: [AUTH-B09]
contrato: LOCK-AUTH-09
verificacion_critica: true
actualizado: 2026-07-07
---

# AUTH-B13 — Pantalla de nueva contraseña (reset)

## Objetivo

Pantalla `/reset-password?token=...&email=...` — formulario de nueva contraseña + confirmación. El `token` (64 caracteres hex) y el `email` provienen de los query parameters del link de recuperación enviado por email. Consume `POST /api/v1/auth/reset-password` (LOCK-AUTH-09). Aplica la misma política de contraseña que el registro (8+ caracteres, 1 mayúscula, 1 minúscula, 1 número) con validación client-side y feedback visual en tiempo real (checklist de requisitos). Tras éxito, redirige a `/login` con mensaje de confirmación.

## Alcance

- **Incluye:**
  - Pantalla `/reset-password` que lee `token` y `email` de `useSearchParams()`.
  - Validación de entrada al montar: si faltan `token` o `email` en la URL → no se muestra el formulario, se muestra mensaje "Enlace inválido o incompleto. Solicita un nuevo enlace de recuperación." con enlace a `/forgot-password`.
  - Si ambos params están presentes: mostrar mensaje contextual "Restableciendo contraseña para usuario@email.com" (solo informativo, el email no se envía al endpoint).
  - Formulario con dos campos: nueva contraseña + confirmación (Zod + React Hook Form).
  - Política de contraseña idéntica a `AUTH-B01` (y `AUTH-B07`): mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número — implementada con schema Zod reutilizado del feature auth (`passwordSchema`).
  - Feedback visual en tiempo real: checklist de los 4 requisitos de contraseña (8+ caracteres, 1 mayúscula, 1 minúscula, 1 número) que se actualiza mientras el usuario escribe, cada ítem con indicador visual de cumplido/no cumplido.
  - Validación de coincidencia entre contraseña y confirmación: bloquea el submit si no coinciden, con mensaje "Las contraseñas no coinciden."
  - Hook de TanStack Query mutation contra `POST /api/v1/auth/reset-password`. El request body incluye `token` (de la URL), `password`, y `password_confirmation`.
  - Manejo de errores:
    - `VALIDATION_ERROR` (422): mostrar mensaje específico del campo fallido (contraseña no cumple política, o passwords no coinciden, o campos faltantes).
    - `RESET_TOKEN_INVALID` (422): mensaje "Este enlace ya no es válido. Solicita uno nuevo." con enlace a `/forgot-password`.
    - `RESET_TOKEN_EXPIRED` (422): mensaje "Este enlace expiró (válido por 60 minutos). Solicita uno nuevo." con enlace a `/forgot-password`.
    - `TOO_MANY_REQUESTS` (429): mensaje "Demasiados intentos. Espera 15 minutos e inténtalo de nuevo."
  - Tras éxito `200`: redirige a `/login` con toast o mensaje "Contraseña actualizada exitosamente. Inicia sesión con tu nueva contraseña."
  - Estado de carga durante el submit (botón deshabilitado + spinner).
- **No incluye (explícitamente fuera de este bloque):**
  - La pantalla de solicitud de recuperación (`AUTH-B12`, `/forgot-password`).
  - Validación asincrónica del token contra la API al cargar la página — el token se valida solo al hacer submit (la API rechazará tokens inválidos/expirados en ese momento).
  - Auto-login tras reset exitoso — el usuario debe iniciar sesión manualmente con la nueva contraseña (consistente con el diseño de `AUTH-B07`: registro no hace auto-login, reset tampoco).
  - Envío del `email` en el body del request — el endpoint solo requiere `token`, `password`, `password_confirmation`.
  - Manejo del caso en que el email en la URL no coincide con el email asociado al token — la API lo detecta (el token está ligado a un usuario específico), no es responsabilidad de la UI.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | URL con `token` y `email` válidos; contraseña nueva que cumple los 4 requisitos + confirmación coincidente | Enviar formulario | `200`, redirige a `/login` con mensaje "Contraseña actualizada exitosamente. Inicia sesión con tu nueva contraseña." |
| 2 | Token inválido o ya usado (`RESET_TOKEN_INVALID`, 422) | Enviar formulario | Mensaje "Este enlace ya no es válido. Solicita uno nuevo." visible sobre el formulario, con enlace "Solicitar nuevo enlace" a `/forgot-password`. El formulario permanece visible por si el usuario quiere reintentar |
| 3 | Token expirado (`RESET_TOKEN_EXPIRED`, 422) | Enviar formulario | Mensaje "Este enlace expiró (válido por 60 minutos). Solicita uno nuevo." con enlace a `/forgot-password` |
| 4 | Usuario escribe contraseña que no cumple algún requisito (ej. solo minúsculas, o 5 caracteres) | Escribir en el campo de contraseña | La checklist de 4 requisitos se actualiza en tiempo real: los requisitos cumplidos muestran ícono de check (verde), los no cumplidos muestran ícono de cross (rojo/gris). El botón de submit permanece habilitado (la validación bloquea en el submit, no antes) |
| 5 | Contraseña no cumple la política (algún requisito pendiente) | Intentar enviar | Validación client-side bloquea el submit: "La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número." |
| 6 | Contraseña y confirmación no coinciden | Intentar enviar | Validación client-side bloquea el submit: "Las contraseñas no coinciden." visible bajo el campo de confirmación |
| 7 | Campos vacíos (contraseña y/o confirmación) | Intentar enviar | Validación client-side bloquea el submit: "La nueva contraseña es obligatoria." y/o "La confirmación es obligatoria." según corresponda |
| 8 | Faltan `token` o `email` en la URL (ej. `/reset-password` sin query params, o solo uno) | Cargar la página | Mensaje "Enlace inválido o incompleto. Solicita un nuevo enlace de recuperación." con enlace a `/forgot-password`. El formulario no se renderiza |
| 9 | Rate limit excedido (`TOO_MANY_REQUESTS`, 429 — 5 intentos/15 minutos por IP) | Enviar formulario repetidamente | Mensaje "Demasiados intentos. Espera 15 minutos e inténtalo de nuevo." — formulario se deshabilita temporalmente |
| 10 | Error de validación del servidor por contraseña que pasa validación client-side pero es rechazada por el servidor (`VALIDATION_ERROR`, 422) | Enviar formulario | Mensaje con el error específico del campo devuelto por la API — caso borde de defensa, improbable si el Zod schema es idéntico |
| 11 | Usuario modifica manualmente el `token` en la URL por un valor inválido | Enviar formulario | La API responde `RESET_TOKEN_INVALID` (422) — la UI muestra el mensaje del caso 2 |

## Contrato

Consume `LOCK-AUTH-09` — endpoint `POST /api/v1/auth/reset-password`. Ver `_state/contracts/CONTRACT_LOCKS.md` §LOCK-AUTH-09 y `api/endpoints/AUTH.md` §POST /api/v1/auth/reset-password.

## Definition of Done

- [ ] `pnpm ci` (type-check + lint + test + build) ejecutado — salida completa pegada.
- [ ] Verificación funcional real (Playwright) recorriendo los 11 casos de la tabla de criterios de aceptación — camino feliz y todos los casos de error.
- [ ] Tipos de request/response usados coinciden exactamente con `LOCK-AUTH-09` (`ResetPasswordRequest: { token: string, password: string, password_confirmation: string }`, `ResetPasswordResponse: { message: string }`).
- [ ] Confirmar que la checklist de requisitos de contraseña se actualiza en tiempo real mientras el usuario escribe — no solo en el submit.
- [ ] Confirmar que el schema de contraseña Zod es idéntico al usado en `AUTH-B07` (8+ chars, 1 mayúscula, 1 minúscula, 1 número) — idealmente reutilizado del mismo archivo de schemas.
- [ ] `web/features/auth/AUTH-reset-password.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01` — sin componentes custom nuevos salvo justificación explícita en "Notas".
- [ ] `web/WEB_API_CLIENT.md` actualizado si el cliente/hook de este bloque introduce un patrón nuevo no documentado.

## Evidencia

## Notas

- La política de contraseña es la misma de `AUTH-B01`/`AUTH-B07`: mínimo 8 caracteres, al menos 1 mayúscula, 1 minúscula, 1 número. El schema Zod debe reutilizarse del feature auth para garantizar consistencia — no duplicarse.
- El `email` en la URL es informativo para la UX ("Restableciendo contraseña para X") pero no se envía al endpoint. La API resuelve el usuario a partir del `token`.
- El endpoint `/auth/reset-password` marca el token como usado tras éxito (un solo uso). Si el usuario intenta usar el mismo link dos veces, la segunda recibe `RESET_TOKEN_INVALID`.
- Esta pantalla es pública (no requiere `access_token`) — la identidad se deriva del token de recuperación.
- El rate limit de 5 intentos/15 min es por IP. Si el usuario comparte IP (ej. oficina), el límite es compartido.
