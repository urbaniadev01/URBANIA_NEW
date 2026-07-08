---
tipo: referencia
proyecto: web
feature: AUTH
actualizado: 2026-07-07
---

# AUTH — Pantalla de registro por invitación

**Bloque que la produce:** [[../../../features/AUTH/blocks/AUTH-B07-pantalla-registro]]
**Tipo:** Página
**Ruta:** `/register/:token`

## Qué muestra

Formulario de registro para usuarios invitados. Lee el token de invitación de la URL (`:token`) y
permite al usuario completar sus datos: nombre completo, contraseña (con complejidad mínima),
confirmación de contraseña, y teléfono opcional.

Si no hay token en la URL, muestra un mensaje de "Enlace inválido" con un enlace a `/login`.

Al pie del formulario hay un enlace "¿Ya tienes cuenta? Inicia sesión" que redirige a `/login`.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Enviar formulario de registro | `useRegisterMutation()` (TanStack Query) | [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-01]] |

## Estados de la vista

- **Carga:** Botón deshabilitado con spinner (`Loader2`) y texto "Creando cuenta...". Todos los
  campos deshabilitados durante el envío.

- **Éxito (201):** Toast verde "Cuenta creada, inicia sesión", redirige a `/login`. Sin auto-login.

- **Error 403 `INVITATION_TOKEN_INVALID`:** Toast rojo "La invitación no es válida o ya fue
  utilizada." — mensaje genérico sin distinguir causa exacta (expirada, consumida, inexistente).

- **Error 409 `EMAIL_ALREADY_REGISTERED`:** Toast rojo "Este email ya está registrado. Inicia sesión
  en lugar de registrarte." — sugiere al usuario usar login en vez de registro.

- **Error 422 `VALIDATION_ERROR`:** Toast rojo con el mensaje de validación del servidor (o texto
  genérico "Datos inválidos. Revisa los campos.").

- **Error 429 (rate limit):** Toast rojo "Demasiados intentos. Espera un minuto antes de volver a
  intentarlo."

- **Validación de cliente (CA4):** Errores inline debajo de cada campo:
  - Contraseña < 8 caracteres, sin mayúscula, sin minúscula, o sin número.
  - Confirmación no coincide con contraseña (`refine` en Zod).
  - Nombre vacío o < 2 caracteres.
  - El submit se bloquea si hay errores de validación — la mutación nunca se llama.

## Permisos

Público — no requiere autenticación. Cualquier persona con un token de invitación válido puede
acceder a esta pantalla y completar el registro.

## Componentes utilizados

Todos de shadcn/ui instalados en `WEB_BOOTSTRAP-B01`:
- `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`
- `Form`, `FormField`, `FormItem`, `FormLabel`, `FormControl`, `FormMessage`
- `Input`
- `Button`
- `Loader2` (lucide-react)
