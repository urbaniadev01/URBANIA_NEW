---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B11
proyectos: [web]
estado: done
depende_de: [AUTH-B08]
contrato: LOCK-AUTH-08
verificacion_critica: true
actualizado: 2026-07-07
---

# AUTH-B11 — Pantalla de enrollment y gestión MFA

## Objetivo

Pantalla `/mfa/enroll` — flujo completo de gestión MFA para usuario autenticado. Cubre tres operaciones: (1) activación en 2 pasos (iniciar enrollment con QR code + recovery codes → confirmar con código TOTP), (2) desactivación de MFA, y (3) regeneración de códigos de respaldo. Consume 4 endpoints bajo LOCK-AUTH-08: `POST /auth/mfa/enroll`, `POST /auth/mfa/confirm`, `POST /auth/mfa/disable`, `POST /auth/mfa/recovery`. Los recovery codes se muestran una sola vez y se eliminan de la UI inmediatamente después.

## Alcance

- **Incluye:**
  - Pantalla `/mfa/enroll` — acceso restringido: si no hay `access_token` en Zustand, redirige a `/login`.
  - **Paso 1 — Iniciar enrollment:**
    - Botón "Activar autenticación en dos pasos" que dispara `POST /api/v1/auth/mfa/enroll` (mutation, requiere Bearer token).
    - Mostrar QR code como imagen (`<img>` con `src` del data URI base64 devuelto por la API) para escanear con app autenticadora.
    - Mostrar los 8 recovery codes debajo del QR, con dos botones de acción: "Copiar todos" (copia al portapapeles vía `navigator.clipboard.writeText()`) y "Descargar TXT" (descarga como archivo `.txt`).
    - Los recovery codes se eliminan del estado del componente al avanzar al paso 2 — no persisten en memoria, localStorage, ni en el DOM.
  - **Paso 2 — Confirmar enrollment:**
    - Input para código TOTP de 6 dígitos generado por la app autenticadora + botón "Verificar y activar" que dispara `POST /api/v1/auth/mfa/confirm`.
    - Tras éxito `200`: toast "MFA activado exitosamente.", la UI transiciona automáticamente al panel de gestión (estado "MFA activo").
    - Tras `MFA_CODE_INVALID` (422): mensaje de error, campo limpio para reintentar, contador de intentos restantes visible (la API permite 5 antes de `MFA_ENROLLMENT_EXPIRED`).
    - Tras `MFA_ENROLLMENT_EXPIRED` (422): mensaje y botón para reiniciar desde el paso 1.
  - **Panel de gestión (visible solo cuando MFA está activo):**
    - Indicador visual de "MFA activo" (badge o texto en verde).
    - Sección "Desactivar autenticación en dos pasos": input de código TOTP + botón "Desactivar" que dispara `POST /api/v1/auth/mfa/disable`.
    - Sección "Regenerar códigos de respaldo": input de código TOTP + botón "Regenerar" que dispara `POST /api/v1/auth/mfa/recovery`. Los nuevos códigos se muestran UNA SOLA VEZ, reemplazando cualquier lista anterior, con botones "Copiar todos" y "Descargar TXT". Se eliminan de la UI al navegar a otra sección o al volver a mostrar el panel.
  - Manejo de errores para todos los endpoints:
    - `UNAUTHENTICATED` (401): redirigir a `/login`.
    - `MFA_ALREADY_ENABLED` (409): transicionar directamente al panel de gestión (no es un error — es el estado actual).
    - `MFA_NOT_ENABLED` (409): mensaje "No tienes MFA activo." (caso borde: otro dispositivo ya lo desactivó).
    - `MFA_CODE_INVALID` (422): mensaje "Código inválido. Intenta de nuevo.", campo limpio.
    - `MFA_ENROLLMENT_NOT_FOUND` (404): mensaje "No hay una activación en curso. Inicia de nuevo.", volver al paso 1.
    - `TOO_MANY_REQUESTS` (429): mensaje específico según endpoint (enroll: "Espera una hora."; verify: "Espera un minuto.").
  - Validación client-side en todos los inputs de código: campo obligatorio, exactamente 6 dígitos numéricos.
- **No incluye (explícitamente fuera de este bloque):**
  - La pantalla de verificación MFA durante login (`AUTH-B10`, `/mfa/verify`).
  - Forzar MFA como requisito de organización o rol — el enrollment es voluntario para cualquier usuario autenticado (consistente con el contrato API).
  - Envío de recovery codes por email, SMS, o cualquier otro canal — solo copia/descarga desde la UI.
  - Animaciones de transición entre pasos (nice-to-have, fuera de alcance).
  - Persistencia del estado de enrollment entre refrescos de página — si el usuario recarga durante el paso 1, debe iniciar de nuevo (el enrollment token en Redis expira en 10 min, manejado del lado API).
  - Recordar si el usuario ya vio/descargó los recovery codes — cada vez que se generan (enroll o regenerate) se muestran como nuevos.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario autenticado sin MFA, llega a `/mfa/enroll` | Click en "Activar autenticación en dos pasos" | `POST /mfa/enroll` → `201`. Se muestra QR code (imagen renderizada desde data URI base64) y lista de 8 recovery codes (formato `XXXXX-XXXXX`) con botones "Copiar todos" y "Descargar TXT" |
| 2 | Usuario en paso 1, QR y recovery codes visibles | Escanea QR con app autenticadora, ingresa código TOTP correcto de 6 dígitos, click en "Verificar y activar" | `POST /mfa/confirm` → `200`, toast "MFA activado exitosamente.", los recovery codes desaparecen de la UI, se muestra el panel de gestión con badge "MFA activo" |
| 3 | Usuario en paso 1, hace click en "Copiar todos" | Click | Los 8 recovery codes se copian al portapapeles (separados por saltos de línea), toast "8 códigos copiados al portapapeles." |
| 4 | Usuario en paso 1, hace click en "Descargar TXT" | Click | Se descarga archivo `urbania-recovery-codes.txt` con los 8 códigos (uno por línea), precedidos por instrucciones de uso |
| 5 | Usuario con MFA activo, en panel de gestión | Ingresa código TOTP correcto en sección "Desactivar", click en "Desactivar" | `POST /mfa/disable` → `200`, toast "MFA desactivado exitosamente.", la UI vuelve a mostrar el botón "Activar autenticación en dos pasos" (paso 1) |
| 6 | Usuario con MFA activo, en panel de gestión | Ingresa código TOTP correcto en sección "Regenerar códigos", click en "Regenerar" | `POST /mfa/recovery` → `200`, se muestran 8 nuevos recovery codes con botones "Copiar todos" y "Descargar TXT". Los códigos anteriores quedan invalidados (manejado por la API) |
| 7 | Usuario NO autenticado (sin `access_token` en Zustand) | Navegar a `/mfa/enroll` | Redirige a `/login` sin mostrar contenido de la pantalla |
| 8 | `access_token` expirado o inválido (`UNAUTHENTICATED`, 401 en cualquier endpoint) | Cualquier acción (enroll, confirm, disable, recovery) | Redirige a `/login` — el interceptor del cliente HTTP central maneja el 401 automáticamente |
| 9 | Usuario ya tiene MFA activo (`MFA_ALREADY_ENABLED`, 409 al iniciar enrollment) | Intentar `POST /mfa/enroll` | La UI detecta el `409` y transiciona directamente al panel de gestión con badge "MFA activo" (no muestra mensaje de error — refleja el estado real) |
| 10 | Código TOTP incorrecto en confirmación (`MFA_CODE_INVALID`, 422) | Enviar formulario en paso 2 | Mensaje "Código inválido. Intenta de nuevo." visible, campo limpio, contador de intentos restantes (ej. "3 de 5 intentos restantes") |
| 11 | Código TOTP incorrecto 5 veces consecutivas → enrollment cancelado (`MFA_ENROLLMENT_EXPIRED`, 422) | Enviar formulario de confirmación por 5ª vez | Mensaje "Demasiados intentos fallidos. La activación fue cancelada. Inicia de nuevo.", la UI vuelve a mostrar el botón "Activar autenticación en dos pasos" (paso 1) |
| 12 | No hay enrollment pendiente (`MFA_ENROLLMENT_NOT_FOUND`, 404) | Intentar confirmar sin haber iniciado enrollment (ej. recargar en paso 2) | Mensaje "No hay una activación en curso. Inicia de nuevo.", volver al paso 1 |
| 13 | Código TOTP incorrecto al desactivar (`MFA_CODE_INVALID`, 422) | Enviar formulario de desactivación | Mensaje "Código inválido. Intenta de nuevo.", campo limpio |
| 14 | Código TOTP incorrecto al regenerar (`MFA_CODE_INVALID`, 422) | Enviar formulario de regeneración | Mensaje "Código inválido. Intenta de nuevo.", campo limpio |
| 15 | No tiene MFA activo al intentar desactivar/regenerar (`MFA_NOT_ENABLED`, 409) | Enviar formulario (caso borde: otro dispositivo desactivó MFA mientras esta pantalla estaba abierta) | Mensaje "No tienes MFA activo.", la UI vuelve al paso 1 |
| 16 | Rate limit de enrollment (`TOO_MANY_REQUESTS`, 429 en `/mfa/enroll`) | Intentar iniciar enrollment repetidamente (3+ intentos/hora) | Mensaje "Demasiados intentos. Espera una hora e inténtalo de nuevo." |
| 17 | Rate limit de verificación (`TOO_MANY_REQUESTS`, 429 en `/mfa/verify`) | Enviar código repetidamente (5+ intentos/minuto) | Mensaje "Demasiados intentos. Espera un minuto e inténtalo de nuevo." |
| 18 | Campo de código vacío en cualquier sección (confirmar, desactivar, regenerar) | Intentar enviar | Validación client-side bloquea el submit: "El código es obligatorio." |
| 19 | Código con formato inválido (menos de 6 dígitos, más de 6, o contiene letras) | Intentar enviar | Validación client-side bloquea el submit: "Ingresa un código de 6 dígitos." |

## Contrato

Consume `LOCK-AUTH-08` — 4 endpoints:
- `POST /api/v1/auth/mfa/enroll` — iniciar enrollment
- `POST /api/v1/auth/mfa/confirm` — confirmar con código TOTP
- `POST /api/v1/auth/mfa/disable` — desactivar MFA
- `POST /api/v1/auth/mfa/recovery` — regenerar códigos de respaldo

Ver `_state/contracts/CONTRACT_LOCKS.md` §LOCK-AUTH-08 y `api/endpoints/AUTH.md` para detalle completo de cada endpoint.

## Definition of Done

- [ ] `pnpm ci` (type-check + lint + test + build) ejecutado — salida completa pegada.
- [ ] Verificación funcional real (Playwright) recorriendo los 19 casos de la tabla de criterios de aceptación — flujo completo de enrollment, desactivación, regeneración, y todos los casos de error.
- [ ] Tipos de request/response usados coinciden exactamente con `LOCK-AUTH-08` para los 4 endpoints (`MfaEnrollResponse`, `MfaConfirmRequest`, `MfaDisableRequest`, `MfaRecoveryRequest`, `MfaRecoveryResponse`).
- [ ] Confirmar que los recovery codes se eliminan del estado del componente al avanzar al paso 2 o al cerrar la vista de regeneración — no persisten en el DOM, `useState`, ni `localStorage`/`sessionStorage`.
- [ ] Confirmar que los botones "Copiar todos" y "Descargar TXT" funcionan correctamente (clipboard y descarga de archivo).
- [ ] `web/features/auth/AUTH-mfa-enroll.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01` — sin componentes custom nuevos salvo justificación explícita en "Notas".
- [ ] `web/WEB_API_CLIENT.md` actualizado si los clientes/hooks de este bloque introducen un patrón nuevo no documentado.

## Evidencia

```
pnpm run ci

> type-check: tsc -b — PASS
> lint: eslint . --max-warnings 0 — PASS
> test: vitest run — 6 files, 50 tests all passed
> build: tsc -b && vite build — built in 12.32s

Test summary:
  src/lib/utils.test.ts         4 tests  ✓
  src/app/App.test.tsx          1 test   ✓
  src/features/auth/__tests__/LoginPage.test.tsx       3 tests  ✓
  src/features/auth/__tests__/MfaVerifyPage.test.tsx   11 tests ✓
  src/features/auth/__tests__/RegisterPage.test.tsx    7 tests  ✓
  src/features/auth/__tests__/MfaEnrollPage.test.tsx   24 tests ✓
```

Correcciones aplicadas:
1. TOO_MANY_REQUESTS — toasts movidos a componente (hooks hacen break silencioso)
2. UNAUTHENTICATED / REFRESH_FAILED — break silencioso en hooks (interceptor maneja redirect)
3. REFRESH_FAILED tipado en MFA_ENROLL_ERROR_CODES + uso en componente
4. retry: false agregado en 4 mutations
5. maxLength={6} agregado en input confirm paso 2
6. Test CA15 recovery path con MFA_NOT_ENABLED agregado
7. Tests TOO_MANY_REQUESTS en disable y recovery agregados

## Notas

- El `enrollment_token` devuelto por `/mfa/enroll` se almacena del lado servidor en Redis (TTL 10 min) asociado al usuario autenticado. La Web no necesita enviarlo en `/mfa/confirm` — la API lo resuelve por el `access_token` del usuario. La respuesta `201` incluye el campo pero este bloque no lo usa.
- Los recovery codes en texto plano solo existen en la respuesta JSON de `/mfa/enroll` y `/mfa/recovery`. La BD solo almacena hashes bcrypt. Si el usuario pierde los códigos, debe regenerarlos (requiere código TOTP válido).
- El contador de intentos restantes en el paso 2 es feedback visual únicamente — la API es la fuente de verdad del límite de 5 intentos. Si el usuario recarga la página, el contador se reinicia en la UI pero la API mantiene el conteo real.
- La validación de código TOTP usa `z.string().length(6).regex(/^\d{6}$/)` — igual en confirm, disable, y recovery.
- El formato de recovery codes es `XXXXX-XXXXX` (5+5 caracteres alfanuméricos con guión). Al copiar/descargar se preserva este formato.
