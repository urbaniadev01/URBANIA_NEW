---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B06
proyectos: [web]
estado: backlog
depende_de: [AUTH-B02, WEB_BOOTSTRAP-B01]
contrato: consume
actualizado: 2026-07-03
---

# AUTH-B06 — Pantalla de login

## Objetivo

Pantalla `/login`: formulario de email + password que consume `POST /auth/login`, guarda el
`access_token` en memoria (Zustand, nunca `localStorage`) y redirige al dashboard.

## Alcance

**Incluye:**
- Pantalla `/login` con formulario (Zod + React Hook Form).
- Hook de TanStack Query mutation contra `POST /auth/login` vía el cliente central
  (`web/WEB_API_CLIENT.md`).
- Manejo de los estados de error del endpoint: `INVALID_CREDENTIALS`, `ACCOUNT_NOT_ACTIVE`, `429`.

**No incluye:**
- Pantalla de registro (`AUTH-B07`, bloque aparte).
- Persistencia de sesión entre refrescos de página — eso depende de `AUTH-B03` (refresh) y es
  responsabilidad del bootstrap de la app, no de esta pantalla.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Email + password correctos | Enviar formulario | Redirige al dashboard, `access_token` en el store |
| 2 | Credenciales incorrectas (`INVALID_CREDENTIALS`) | Enviar formulario | Mensaje de error genérico visible, sin indicar cuál campo falló |
| 3 | Cuenta no activa (`ACCOUNT_NOT_ACTIVE`) | Enviar formulario | Mensaje específico distinto al del caso 2 |
| 4 | Rate limited (`429`) | Enviar formulario repetidamente | Mensaje de "demasiados intentos", formulario no queda en loop de reintento |
| 5 | Campos vacíos | Intentar enviar | Validación de cliente bloquea el submit antes de llamar a la API |

## Contrato

**Consume** `LOCK-AUTH-02` (`POST /auth/login`). No puede pasar a `ready` hasta que ese lock exista
en `_state/contracts/CONTRACT_LOCKS.md` — ver [[../../../_system/04_CROSS_PROJECT]] §3.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real (Playwright) de los 5 casos de la tabla — no solo `pnpm ci`.
- [ ] Confirmar que el `access_token` nunca aparece en `localStorage`/`sessionStorage` (inspección
      del storage del navegador durante la verificación, evidencia pegada).
- [ ] Tipos de request/response usados coinciden exactamente con `LOCK-AUTH-02`.
- [ ] `web/features/auth/AUTH-login.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados son los instalados en `WEB_BOOTSTRAP-B01` (form, input, button, toast para
      errores) — sin componentes custom nuevos salvo justificación explícita en "Notas".

## Evidencia

_Vacío._

## Notas

Depende también de `WEB_BOOTSTRAP-B01` (librería de componentes instalada) — ver
[[../../WEB_BOOTSTRAP/PANORAMA]] y [[../../../web/adr/ADR-WEB-001-libreria-componentes]]. Esta
pantalla es un caso estándar de formulario — no requiere referencia visual previa (ver
`web/WEB_VISUAL_STANDARDS.md` §2).
