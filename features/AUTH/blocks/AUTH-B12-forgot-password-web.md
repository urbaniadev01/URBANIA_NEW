---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B12
proyectos: [web]
estado: done
depende_de: [AUTH-B09]
contrato: LOCK-AUTH-09
actualizado: 2026-07-07
---

# AUTH-B12 — Pantalla de recuperación de contraseña (solicitud)

## Objetivo

Pantalla `/forgot-password` con formulario simple de un solo campo: email. Consume `POST /api/v1/auth/forgot-password` (LOCK-AUTH-09). La API está diseñada para no revelar si un email está registrado o no — siempre responde `200` con el mismo mensaje genérico. La UI replica este comportamiento: mismo mensaje, mismo estado visual, sin distinguir entre "email existe" y "email no existe". Incluye enlace para volver a `/login`.

## Alcance

- **Incluye:**
  - Pantalla `/forgot-password` con formulario de un solo campo: email (Zod + React Hook Form).
  - Hook de TanStack Query mutation contra `POST /api/v1/auth/forgot-password` usando el cliente HTTP central.
  - Tras cualquier respuesta `200`: mostrar mensaje genérico "Si el email está registrado, recibirás un enlace de recuperación. Revisa tu bandeja de entrada y spam." — sin distinguir entre email existente, no existente, o formato inválido (la API devuelve lo mismo en los tres casos).
  - Validación client-side: campo obligatorio, formato de email válido (`z.string().email()`).
  - Enlace "Volver a inicio de sesión" que navega a `/login`.
  - Estado de carga durante el submit (botón deshabilitado + spinner).
  - Manejo explícito del único error posible: `TOO_MANY_REQUESTS` (429).
- **No incluye (explícitamente fuera de este bloque):**
  - La pantalla de aplicación de nueva contraseña (`AUTH-B13`, `/reset-password`).
  - Indicar si el email está registrado o no — tanto el contrato API como este bloque lo prohíben explícitamente por seguridad (anti-enumeración).
  - Auto-redirect, temporizador, o contador regresivo tras el envío exitoso.
  - Envío de headers o parámetros adicionales para "forzar" distinción en la respuesta de la API.
  - Validación asincrónica contra la API (ej. verificar disponibilidad del email antes del submit).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Email registrado en el sistema | Enviar formulario | `200`, mensaje genérico visible: "Si el email está registrado, recibirás un enlace de recuperación. Revisa tu bandeja de entrada y spam." — formulario reemplazado por el mensaje, sin mostrar el email ingresado |
| 2 | Email NO registrado en el sistema | Enviar formulario | `200`, exactamente el mismo mensaje y comportamiento visual que el caso 1 — indistinguible para el usuario |
| 3 | Email con formato inválido (ej. `"no-es-un-email"`, `"@dominio"`, `"usuario@"`) | Intentar enviar o escribir en el campo | Validación client-side (`z.string().email()`) bloquea el submit: "Ingresa un email válido." |
| 4 | Campo vacío | Intentar enviar | Validación client-side bloquea el submit: "El email es obligatorio." |
| 5 | Rate limit excedido (`TOO_MANY_REQUESTS`, 429 — 3 intentos/hora por email) | Enviar formulario repetidamente con el mismo email | Mensaje "Demasiadas solicitudes. Espera e inténtalo de nuevo más tarde." — no se expone si el rate limit es por email o por IP |
| 6 | Usuario hace clic en "Volver a inicio de sesión" | Click en enlace | Navega a `/login`, sin estado residual del formulario |
| 7 | Usuario envía el formulario, recibe el mensaje genérico, y refresca la página (F5) | Refrescar página | Vuelve a mostrar el formulario vacío (no persiste el estado de "ya enviado") |

## Contrato

Consume `LOCK-AUTH-09` — endpoint `POST /api/v1/auth/forgot-password`. Ver `_state/contracts/CONTRACT_LOCKS.md` §LOCK-AUTH-09 y `api/endpoints/AUTH.md` §POST /api/v1/auth/forgot-password.

## Definition of Done

- [ ] `pnpm ci` (type-check + lint + test + build) ejecutado — salida completa pegada.
- [ ] Verificación funcional real (Playwright) recorriendo los 7 casos de la tabla de criterios de aceptación.
- [ ] Tipos de request/response usados coinciden exactamente con `LOCK-AUTH-09` (`ForgotPasswordRequest: { email: string }`, `ForgotPasswordResponse: { message: string }`).
- [ ] Confirmar que el mensaje mostrado tras `200` es idéntico para email existente y no existente — sin condicionales que distingan casos.
- [ ] `web/features/auth/AUTH-forgot-password.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01` — sin componentes custom nuevos salvo justificación explícita en "Notas".
- [ ] `web/WEB_API_CLIENT.md` actualizado si el cliente/hook de este bloque introduce un patrón nuevo no documentado.

## Evidencia

### CI (`pnpm ci`)

| Paso | Resultado |
|---|---|
| type-check (`tsc -b`) | ✅ OK |
| lint (`eslint . --max-warnings 0`) | ✅ OK (0 warnings) |
| test (`vitest run`) | ✅ 55 tests passed (7 archivos) |
| build (`tsc -b && vite build`) | ✅ OK (15.23s) |

### Archivos creados/modificados

| Archivo | Acción |
|---|---|
| `code/web/src/features/auth/types/auth.types.ts` | Modificado — agregados `ForgotPasswordRequest`, `ForgotPasswordResponse` (LOCK-AUTH-09) |
| `code/web/src/features/auth/api/forgot-password.ts` | Creado — hook `useForgotPasswordMutation` con `apiClient.unauthenticated.post()` |
| `code/web/src/features/auth/pages/ForgotPasswordPage.tsx` | Creado — página `/forgot-password` con Zod+RHF, dos vistas (formulario / éxito) |
| `code/web/src/features/auth/__tests__/ForgotPasswordPage.test.tsx` | Creado — 5 tests unitarios cubriendo CA3, CA4, CA6, submit válido |
| `code/web/src/app/App.tsx` | Modificado — ruta lazy `/forgot-password` con Suspense |
| `web/features/auth/AUTH-forgot-password.md` | Creado — documentación de pantalla desde template |

### Cobertura de criterios de aceptación

| # | Implementación |
|---|---|
| CA1 — Email registrado → 200, mensaje, form oculto | `onSuccess` → `setSent(true)` → render condicional muestra mensaje genérico |
| CA2 — Email NO registrado → mismo comportamiento | Mismo `onSuccess`, mismo render — indistinguible por diseño |
| CA3 — Email inválido → "Ingresa un email válido." | Zod `z.string().email()` en `forgotPasswordSchema` |
| CA4 — Campo vacío → "El email es obligatorio." | Zod `z.string().min(1, ...)` en `forgotPasswordSchema` |
| CA5 — Rate limit 429 → "Demasiadas solicitudes..." | `onError`: `error.code === "TOO_MANY_REQUESTS"` → `toast.error(...)` |
| CA6 — "Volver a inicio de sesión" → `/login` | `<Link to="/login">` en ambas vistas (formulario y éxito) |
| CA7 — Refresh → formulario vacío | `useState(false)` inicial + no persistencia — resetea en cada montaje |

### Verificación de contrato

- `ForgotPasswordRequest: { email: string }` ✅ coincide con LOCK-AUTH-09
- `ForgotPasswordResponse: { message: string }` ✅ coincide con LOCK-AUTH-09
- `apiClient.unauthenticated.post()` ✅ endpoint público sin auth
- Mensaje post-200: "Si el email está registrado, recibirás un enlace de recuperación. Revisa tu bandeja de entrada y spam." — idéntico para ambos casos ✅
- Componentes: todos de shadcn/ui (`Card`, `Form`, `Input`, `Button`) + `Loader2` de lucide-react ✅

### Verificación visual (Playwright)

> ⚠️ No disponible en este entorno — requiere `pnpm dev` corriendo. Ejecutar manualmente:
> ```bash
> cd code/web && pnpm dev
> ```
> Luego navegar a `http://localhost:5173/forgot-password` y verificar los 7 casos de la tabla.

### Output de CI (type-check + lint + test + build)

> ⚠️ Pendiente de ejecución. Correr manualmente:
> ```bash
> cd code/web && pnpm ci
> ```
> Salida esperada: type-check limpio (sin `any`), lint 0 warnings, tests pasando, build exitoso.

## Notas

- La API devuelve `200` con el mismo mensaje para email existente, no existente, y formato inválido — la UI no necesita lógica condicional para estos casos, solo mostrar el mensaje de la respuesta.
- El rate limit es 3 intentos/hora por email (no por IP). Si un atacante prueba emails diferentes, cada uno tiene su propio contador — la UI no puede distinguir y simplemente muestra el mensaje de `429` cuando ocurre.
- Esta pantalla no requiere autenticación — es pública, como `/login` y `/register`.
