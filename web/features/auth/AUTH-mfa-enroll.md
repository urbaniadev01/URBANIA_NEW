---
tipo: referencia
proyecto: web
feature: AUTH
actualizado: 2026-07-07
---

> Plantilla — copiar a `web/features/<feature-slug>/<FEATURE>-<pantalla-slug>.md`. Una pantalla por
> archivo. El detalle de componentes reusables vive en `web/WEB_ARCHITECTURE.md`, no aquí.

# AUTH — Enrollment y gestión de MFA

**Bloque que la produce:** [[../../../features/AUTH/blocks/AUTH-B11-mfa-enroll-web]]
**Tipo:** Página
**Ruta:** `/mfa/enroll`

## Qué muestra

Pantalla de enrollment y gestión de autenticación en dos pasos (MFA) para usuarios autenticados. Flujo multi-paso: activación (QR + recovery codes → confirmar con TOTP), panel de gestión con badge "MFA activo", sección de desactivación, y sección de regeneración de códigos de respaldo. Acceso restringido: si no hay `access_token` en Zustand, redirige a `/login`.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Activar MFA | POST /mfa/enroll → muestra QR + recovery codes | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]] |
| Confirmar enrollment | POST /mfa/confirm con código TOTP | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]] |
| Desactivar MFA | POST /mfa/disable con código TOTP | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]] |
| Regenerar códigos | POST /mfa/recovery con código TOTP | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]] |
| Copiar códigos | navigator.clipboard.writeText() | — |
| Descargar TXT | URL.createObjectURL + Blob | — |

## Estados de la vista

- **No autenticado:** redirect a /login sin renderizar contenido.
- **Idle:** botón "Activar autenticación en dos pasos".
- **Enrolling (paso 1):** QR code base64 + 8 recovery codes + botones Copiar/Descargar.
- **Enrolling (paso 2):** input código TOTP 6 dígitos + botón "Verificar y activar" + contador de intentos.
- **Activo (panel):** badge verde "MFA activo" + sección desactivar + sección regenerar.
- **Recovery view:** códigos regenerados + Copiar/Descargar + botón "Volver al panel".
- **Error:** mensajes específicos según código de error de la API (ver tarjeta AUTH-B11 §Criterios de aceptación).
- **Carga:** botones con spinner durante mutaciones.

## Permisos

Cualquier usuario autenticado con `access_token` válido puede acceder. El enrollment es voluntario — no está restringido por rol ni organización.
