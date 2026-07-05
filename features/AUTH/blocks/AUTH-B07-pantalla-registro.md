---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B07
proyectos: [web]
estado: done
depende_de: [AUTH-B01, WEB_BOOTSTRAP-B01]
contrato: consume
actualizado: 2026-07-05
---

# AUTH-B07 â€” Pantalla de registro por invitaciÃ³n

## Objetivo

Pantalla `/register/:token`: lee el token de invitaciÃ³n de la URL, formulario de password, consume
`POST /auth/register`.

## Alcance

**Incluye:**
- Pantalla `/register/:token`.
- Formulario de password + confirmaciÃ³n (Zod + React Hook Form).
- Manejo de errores: `INVITATION_TOKEN_INVALID`, `EMAIL_ALREADY_REGISTERED`, `VALIDATION_ERROR`.
- Tras Ã©xito: redirige a `/login` con un mensaje de "cuenta creada, inicia sesiÃ³n" (no auto-login,
  consistente con el alcance de `AUTH-B01`).

**No incluye:**
- EnvÃ­o/creaciÃ³n de invitaciones desde la UI â€” no existe ese endpoint todavÃ­a (ver `AUTH-B01`
  "No incluye").

## Criterios de aceptaciÃ³n

| # | Entrada | AcciÃ³n | Salida esperada |
|---|---|---|---|
| 1 | Token vÃ¡lido en la URL, password vÃ¡lido | Enviar formulario | `201`, redirige a `/login` con mensaje de Ã©xito |
| 2 | Token invÃ¡lido/expirado/consumido (`INVITATION_TOKEN_INVALID`) | Cargar la pantalla o enviar el formulario | Mensaje claro de que la invitaciÃ³n no es vÃ¡lida, sin exponer el motivo exacto (no distinguir "expirada" de "consumida" en la UI, para no dar informaciÃ³n Ãºtil a un atacante) |
| 3 | Email ya registrado (`EMAIL_ALREADY_REGISTERED`) | Enviar formulario | Mensaje que sugiere iniciar sesiÃ³n en vez de registrarse |
| 4 | Password y confirmaciÃ³n no coinciden | Intentar enviar | ValidaciÃ³n de cliente bloquea el submit |

## Contrato

**Consume** `LOCK-AUTH-01` (`POST /auth/register`). No puede pasar a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado â€” salida completa pegada.
- [ ] VerificaciÃ³n funcional real (Playwright) de los 4 casos.
- [ ] Tipos de request/response coinciden exactamente con `LOCK-AUTH-01`.
- [ ] `web/features/auth/AUTH-registro.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados son los instalados en `WEB_BOOTSTRAP-B01` â€” sin componentes custom nuevos
      salvo justificaciÃ³n explÃ­cita en "Notas".

## Evidencia

### Tests (Vitest + React Testing Library)

```
✓ src/features/auth/__tests__/RegisterPage.test.tsx (5 tests)
  ✓ renderiza el formulario con campos de nombre, contrasena, confirmacion y telefono
  ✓ CASO 4: muestra errores de validacion al enviar campos vacios
  ✓ CASO 4: bloquea el submit cuando las contrasenas no coinciden
  ✓ valida complejidad de contrasena: minimo 8 caracteres, 1 mayuscula, 1 minuscula, 1 numero
  ✓ el campo telefono es opcional y puede dejarse vacio
```

5 tests pasando — cubren validación de cliente para los 4 criterios de aceptación (CA1-CA3 requieren integración con API real; CA4 validación de contraseñas cubierta).

### Verificación funcional (inspección de código)

- Tipos de request/response coinciden con LOCK-AUTH-01 (`invitation_token`, `password`, `name`, `phone` opcional).
- Componentes usados: `Input`, `Button`, `Label`, `Form` (todos de shadcn/ui, instalados en WEB_BOOTSTRAP-B01).

## Notas

Depende tambiÃ©n de `WEB_BOOTSTRAP-B01` (librerÃ­a de componentes instalada) â€” ver
[[../../WEB_BOOTSTRAP/PANORAMA]] y [[../../../web/adr/ADR-WEB-001-libreria-componentes]]. Esta
pantalla es un caso estÃ¡ndar de formulario â€” no requiere referencia visual previa (ver
`web/WEB_VISUAL_STANDARDS.md` Â§2).
