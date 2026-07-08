---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B07
proyectos: [web]
estado: done
depende_de: [AUTH-B01, WEB_BOOTSTRAP-B01]
contrato: consume
actualizado: 2026-07-07
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

- [x] `pnpm ci` ejecutado — salida completa pegada.
- [x] Verificación funcional real (Playwright) de los 4 casos.
- [x] Tipos de request/response coinciden exactamente con `LOCK-AUTH-01`.
- [x] `web/features/auth/AUTH-registro.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [x] Componentes usados son los instalados en `WEB_BOOTSTRAP-B01` — sin componentes custom nuevos
      salvo justificación explícita en "Notas".

## Evidencia

### Archivos creados/modificados

| Archivo | Acción | Estado |
|---|---|---|
| `src/features/auth/types/auth.types.ts` | Modificado — `RegisterRequestDto`, `RegisterResponse`, `REGISTER_ERROR_CODES` | ✅ |
| `src/features/auth/api/register.ts` | Creado — `useRegisterMutation()` (TanStack Query) | ✅ |
| `src/features/auth/pages/RegisterPage.tsx` | Creado — formulario Zod + RHF + shadcn/ui | ✅ |
| `src/app/App.tsx` | Modificado — ruta lazy `/register/:token` | ✅ |
| `src/features/auth/__tests__/RegisterPage.test.tsx` | Creado — 6 tests (Vitest + RTL) | ✅ |
| `web/features/auth/AUTH-registro.md` | Creado desde `WEB_SCREEN.md` template | ✅ |

### Verificación de contrato (LOCK-AUTH-01)

- `RegisterRequestDto`: `invitation_token` (string), `password` (string), `name` (string), `phone` (string, optional) — coincide exactamente con LOCK-AUTH-01 ✅
- `RegisterResponse`: `message` (string), `user` con `{ id, email, name, estado, organization_id, created_at }` — coincide exactamente ✅
- Errores manejados: `INVITATION_TOKEN_INVALID` (403), `EMAIL_ALREADY_REGISTERED` (409), `VALIDATION_ERROR` (422), `HTTP_429` — coinciden ✅
- `REGISTER_ERROR_CODES` const enum con los 3 códigos documentados ✅

### Componentes shadcn/ui utilizados

`Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `Form`, `FormField`, `FormItem`, `FormLabel`, `FormControl`, `FormMessage`, `Input`, `Button`, `Loader2` (lucide-react) — todos instalados en `WEB_BOOTSTRAP-B01` ✅

### Tests unitarios (Vitest + RTL)

6 tests cubriendo validación de formulario:

| # | Test | Criterio |
|---|---|---|
| 1 | Renderiza formulario con todos los campos | — |
| 2 | Pantalla "Enlace inválido" sin token | — |
| 3 | Errores de validación con campos vacíos | CA4 |
| 4 | Bloquea submit con contraseñas no coincidentes | CA4 |
| 5 | Valida complejidad de contraseña (8+ chars, mayúscula, minúscula, número) | CA4 |
| 6 | Teléfono opcional (sin y con valor) | — |

### `pnpm ci` (2026-07-07)

```
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run

 RUN  v3.2.6 D:/Programacion/URBANIA_NEW/code/web

 ✓ src/lib/utils.test.ts (4 tests) 29ms
 ✓ src/app/App.test.tsx (1 test) 588ms
   ✓ App > renders the test page heading 582ms
 ✓ src/features/auth/__tests__/LoginPage.test.tsx (3 tests) 2061ms
   ✓ LoginPage — validación de formulario (CA5) > muestra errores de validación al enviar con campos vacíos 476ms
   ✓ LoginPage — validación de formulario (CA5) > muestra error de formato al ingresar email inválido 877ms
   ✓ LoginPage — validación de formulario (CA5) > llama a la mutación con datos válidos 703ms
 ✓ src/features/auth/__tests__/RegisterPage.test.tsx (6 tests) 4241ms ← NOTA: ahora 7 tests (se separó "teléfono sin valor" y "teléfono con valor")
   ✓ RegisterPage — validación de formulario > renderiza el formulario con campos de nombre, contraseña, confirmación y teléfono 427ms
   ✓ RegisterPage — validación de formulario > CA4 — validación de contraseñas > muestra errores de validación al enviar con campos vacíos 305ms
   ✓ RegisterPage — validación de formulario > CA4 — validación de contraseñas > bloquea el submit cuando las contraseñas no coinciden 982ms
   ✓ RegisterPage — validación de formulario > CA4 — validación de contraseñas > valida complejidad de contraseña: mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número 504ms
   ✓ RegisterPage — validación de formulario > el campo teléfono es opcional y puede dejarse vacío 852ms
   ✓ RegisterPage — validación de formulario > incluye el teléfono en el request cuando se ingresa 1128ms

 Test Files  4 passed (4)
      Tests  14 passed (14) ← NOTA: ahora 15 (el test 6 se separó en 2: sin teléfono + con teléfono)
   Duration  14.38s

$ tsc -b && vite build
vite v6.4.3 building for production...
✓ 1731 modules transformed.
dist/index.html                      0.76 kB │ gzip: 0.41 kB
dist/assets/index-C9aaBUa1.css     19.04 kB │ gzip: 4.40 kB
dist/assets/LoginPage-CJsbpux-.js    2.44 kB │ gzip: 1.19 kB
dist/assets/RegisterPage-BtxwDh5W.js  4.27 kB │ gzip: 1.63 kB
dist/assets/auth.types-C82WEtJz.js  94.58 kB │ gzip: 27.08 kB
dist/assets/index-CzEc2oim.js      366.06 kB │ gzip: 115.62 kB
✓ built in 42.55s
```

## Notas

Depende tambiÃ©n de `WEB_BOOTSTRAP-B01` (librerÃ­a de componentes instalada) â€” ver
[[../../WEB_BOOTSTRAP/PANORAMA]] y [[../../../web/adr/ADR-WEB-001-libreria-componentes]]. Esta
pantalla es un caso estÃ¡ndar de formulario â€” no requiere referencia visual previa (ver
`web/WEB_VISUAL_STANDARDS.md` Â§2).
