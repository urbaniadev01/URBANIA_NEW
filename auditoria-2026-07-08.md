---
tipo: estado
proyecto: shared
actualizado: 2026-07-08
---

# Auditoría 2026-07-08 — Código, documentación y código-vs-documentación

> Auditoría de integridad ejecutada fuera del pipeline de OpenCode (sesión de Claude Code, modo
> asesoría). Cubre `code/api`, `code/web`, y el vault completo (`_state/`, `features/`, `api/`,
> `shared/`, `.opencode/agents/`). Objetivo: identificar qué falta para llegar a un MVP de
> autenticación listo para producción.
>
> **Para OpenCode:** este documento es un input de trabajo, no una tarjeta de bloque. Las acciones
> de la sección "Plan de acción" deben ejecutarse siguiendo el método normal — bloques nuevos o
> reapertura de bloques existentes, con su propio DoD, evidencia y verificación — no como parches
> directos sin proceso. Donde una acción implica reabrir un bloque `done`, hacerlo explícito.

## Resumen

```
┌─────────────────────────────────────────────────────────────────────────┐
│  AUDIT LOG · 2026-07-08 · trigger: bajo demanda (usuario)               │
├────┬──────────────────────────────────────────────┬──────────┬─────────┤
│ #  │ Hallazgo                                      │ Severidad│ Bloque  │
├────┼──────────────────────────────────────────────┼──────────┼─────────┤
│ 1  │ Ruta /dashboard no existe, login navega ahí   │ ❌       │ B06/web │
│ 2  │ Login no maneja mfa_required                  │ ❌       │ AUTH-B06│
│ 3  │ B10/B11/B12 done sin DoD ni verify-council     │ ❌       │ B10-B12 │
│ 4  │ BLOCKS.md dice backlog, tarjetas dicen done   │ ❌       │ AUTH    │
│ 5  │ Falta cierre CHANGELOG/release-council Fase 2 │ ❌       │ AUTH    │
│ 6  │ code/api sin commits del feature AUTH         │ ❌       │ infra   │
│ 7  │ RequireMfa implementado, nunca aplicado       │ ⚠️       │ AUTH-B08│
│ 8  │ E2E solo cubre login, resto sin Playwright    │ ⚠️       │ B07/B10-13│
│ 9  │ tests/Integration y tests/Security vacíos     │ ⚠️       │ api     │
│ 10 │ Docs de arquitectura desactualizadas          │ ⚠️       │ docs    │
│ 11 │ context-reader no listado en 06_AGENT_ROLES   │ ⚠️       │ gobernanza│
│ 12 │ .env.example sin REDIS_CLIENT                 │ ⚠️       │ api     │
├────┴──────────────────────────────────────────────┴──────────┴─────────┤
│  Resumen: 12 hallazgos (6 ❌, 6 ⚠️) · severidad: Crítico para MVP        │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 🔴 Hallazgos críticos (bloquean el MVP)

### 1. El flujo de login está roto end-to-end
- **Archivo:** `code/web/src/features/auth/api/login.ts:29` — `navigate("/dashboard")` en `onSuccess`.
- **Archivo:** `code/web/src/app/App.tsx` — no define ninguna ruta `/dashboard` ni catch-all 404.
  Rutas existentes: `/`, `/login`, `/register/:token?`, `/mfa/verify`, `/mfa/enroll`,
  `/forgot-password`, `/reset-password`.
- **Efecto:** todo login exitoso termina en pantalla en blanco. No existe ninguna pantalla de
  aplicación (dashboard/home) — `/` sigue sirviendo `TestPage.tsx`, la demo de shadcn/ui de
  WEB_BOOTSTRAP-B01.
- **Agravante:** el único E2E existente, `e2e/auth/login.spec.ts`, asume
  `waitForURL("**/dashboard")` — el propio test de aceptación fallaría si se corriera contra la app
  real.

### 2. El login nunca fue conectado al flujo de MFA
- **Referencia:** `features/AUTH/BLOCKS.md:52` — AUTH-B06 marcado `done` con advertencia sin
  resolver: *"⚠️ requiere modificación: manejar respuesta mfa_required de AUTH-B08"*.
- **Confirmado en código:** `login.ts` no tiene ninguna rama que detecte `mfa_required` en la
  respuesta ni redirija a `/mfa/verify`.
- **Inconsistencia adicional:** `mfa-verify.ts` redirige tras verificar a `/` (TestPage), mientras
  `login.ts` redirige a `/dashboard` (inexistente) — los dos flujos post-autenticación ni siquiera
  coinciden entre sí.

### 3. Tres bloques `done` sin la evidencia ni la verificación que el método exige
Violación de `_system/01_PRINCIPLES.md` §4 y `_system/05_DEFINITION_OF_DONE.md` (evidencia real +
verificación independiente + `verify-council` cuando `verificacion_critica: true`):

| Bloque | Checklist DoD | Evidencia | verify-council |
|---|---|---|---|
| `features/AUTH/blocks/AUTH-B10-*.md` | 7/8 — falta verificación funcional Playwright real | Playwright "validado estructuralmente", no ejecutado | `verificacion_critica: true`, sin registro |
| `features/AUTH/blocks/AUTH-B11-*.md` | 0/8, todo sin marcar | Solo resumen de CI | `verificacion_critica: true`, sin registro |
| `features/AUTH/blocks/AUTH-B12-*.md` | 0/7, todo sin marcar | La propia tarjeta dice: *"⚠️ Pendiente de ejecución. Correr manualmente: `cd code/web && pnpm ci`"* | — |

El código de estas tres pantallas existe y es sustancial (p. ej. `MfaEnrollPage.test.tsx`, 847
líneas / 24 casos) — no es trabajo fantasma, es proceso de verificación saltado. Los hallazgos #1 y
#2 son exactamente el tipo de bug que ese proceso saltado debía atrapar.

### 4. `features/AUTH/BLOCKS.md` contradice `BOARD.md` y las tarjetas
`features/AUTH/BLOCKS.md:56-59` lista AUTH-B10, B11, B12, B13 como `backlog`; `_state/BOARD.md` y
las tarjetas individuales (misma fecha, 2026-07-07) dicen `done/done/done/ready`. Por la regla "un
dato, un dueño", la tarjeta manda — pero el índice de features no se actualiza de forma confiable,
lo cual facilitó que nadie notara los hallazgos #1 y #2 a tiempo.

### 5. Falta el cierre de proceso para Fase 2 Web (AUTH-B10–B13)
`_state/CHANGELOG.md` SHIP-010 solo **autoriza** que B10-B13 pasen de `backlog` a `ready`. No hay
SHIP-011/012/013 con veredicto de release-council ni entrada de cierre, requerido por
`_system/03_LIFECYCLE.md` §6 / `_system/04_CROSS_PROJECT.md` §2 antes de considerar Fase 2 Web
realmente enviada.

### 6. Repos git anidados sin versionar — riesgo real de pérdida de trabajo
- `code/api/.git` y `code/web/.git` son repos git **independientes**, no submódulos (`.gitmodules`
  no existe, no hay gitlink en el repo raíz). Un clon del repo raíz no trae código.
- **`code/api`:** el repo interno tiene un único commit (`c98817a`, solo esqueleto Laravel inicial).
  **Todo el feature AUTH (9 bloques, ~30 archivos modificados + ~20 nuevos) está sin commitear ni
  siquiera en el repo interno.** Sin red de seguridad de git ante pérdida del working directory.
- `code/web/.git` tiene su commit al día (`a585f3d`, solo WEB_BOOTSTRAP-B01); el resto del feature
  AUTH web (B06, B07, B10-B13) tampoco está commiteado ahí.

---

## 🟡 Hallazgos importantes

### 7. Middleware `RequireMfa` implementado pero nunca aplicado
`src/Mfa/.../RequireMfa` registrado en `bootstrap/app.php` y testeado, pero no usado en ninguna ruta
de `routes/api.php`. El único endpoint protegido de ejemplo (`/organizations/{organization}/admin`)
solo aplica `RequirePermission`.

### 8. Cobertura E2E desigual
Playwright solo cubre login (`e2e/auth/login.spec.ts`, 5 casos). Register, MFA enroll/verify, y
forgot/reset-password no tienen ningún test E2E, pese a estar marcados `done`.

### 9. `tests/Integration/` y `tests/Security/` vacíos
Declarados en `phpunit.xml` y con scripts dedicados en `composer.json` (`test:integration`,
`test:security`), pero solo contienen `.gitkeep`.

### 10. Documentación de arquitectura desactualizada
- `api/API_ARCHITECTURE.md` §5: *"Auth ... En desarrollo — bloques AUTH-B01 al AUTH-B04 done"* —
  no refleja B05/B08/B09 ni que el feature está `SHIPPED`.
- `features/API_BOOTSTRAP/BLOCKS.md` y `features/WEB_BOOTSTRAP/BLOCKS.md`: prosa residual
  ("actualmente en `backlog` tras reset 2026-07-05") que contradice sus propias tablas (`done`).
- `_state/contracts/CONTRACT_LOCKS.md`: banner desactualizado (AUTH-B09 en `verifying`, la tarjeta
  real dice `done`); evidencia de AUTH-B03 conserva frase residual de borrador previo.

### 11. Drift de gobernanza en agentes/roles
- `.opencode/agents/context-reader.md` existe y lo usan los orquestadores api/web, pero no está en
  `_system/06_AGENT_ROLES.md` (que declara ser espejo literal obligatorio).
- MCPs `urbania-db` y `playwright` declarados en `opencode.json`, no mencionados en
  `06_AGENT_ROLES.md`.

### 12. `.env.example` incompleto
`REDIS_CLIENT=predis` presente en `.env` real y `phpunit.xml`, ausente en `.env.example` pese a que
`predis/predis` es dependencia obligatoria.

---

## 🟢 Lo que está sólido

- API de Auth (AUTH-B01 a B05, B08, B09): implementación real, sin stubs, tests de feature extensos
  (2,293 líneas Pest), JWT RS256 correcto, cookies con flags correctos, RBAC con invalidación de
  caché por eventos Eloquent, MFA TOTP + recovery codes bien implementado.
- shadcn/ui + Tailwind configurado de forma coherente y realmente usado.
- PHPStan nivel 10 con lista de excepciones curada, no un bypass general.
- Contract-first gating (`CONTRACT_LOCKS.md`) funcionó correctamente en Fase 1 (B06/B07 esperaron
  los locks de B01/B02).

---

## Plan de acción para OpenCode

**Fase 0 — Asegurar el trabajo existente (antes de cualquier otra cosa)**
1. Commitear todo el trabajo pendiente dentro de `code/api` y `code/web` a sus repos internos como
   red de seguridad inmediata.
2. Decidir modelo de versionado: convertir `code/api`/`code/web` en submódulos git reales
   (`git submodule add` + `.gitmodules` + pin de commit en el repo raíz) o eliminar los `.git`
   internos y trackear directo desde el repo raíz.

**Fase 1 — Cerrar el flujo de autenticación real**
3. Definir y crear la ruta `/dashboard` (o la home real post-login); quitar `TestPage` de `/`.
4. Modificar `login.ts` para detectar `mfa_required` en la respuesta y redirigir a `/mfa/verify` —
   es la modificación pendiente de AUTH-B06 ya señalada en `BLOCKS.md`.
5. Unificar destino post-MFA-verify con destino post-login.
6. Re-ejecutar el DoD completo de AUTH-B10, B11, B12 (Playwright real, no solo validación
   estructural) y pasar por `verify-council` donde `verificacion_critica: true` lo exige, antes de
   confirmar `done`.

**Fase 2 — Cerrar los gates de proceso saltados**
7. Emitir entrada de CHANGELOG y veredicto de release-council pendiente para Fase 2 Web
   (AUTH-B10–B13).
8. Sincronizar `features/AUTH/BLOCKS.md` con el estado real de las tarjetas.
9. Limpiar docs stale: `API_ARCHITECTURE.md`, banner de `CONTRACT_LOCKS.md`, prosa residual en
   `API_BOOTSTRAP/BLOCKS.md` y `WEB_BOOTSTRAP/BLOCKS.md`.

**Fase 3 — Cobertura mínima de confianza antes de producción**
10. Agregar al menos un E2E Playwright real por flujo crítico (register, MFA enroll, MFA verify,
    forgot/reset password).
11. Decidir si `tests/Integration/` y `tests/Security/` se llenan con contenido real o se retira el
    scaffolding vacío.

**Fuera del camino crítico de MVP**
- Aplicar `RequireMfa` a endpoints administrativos reales (si el negocio lo requiere).
- Resolver drift de gobernanza (`context-reader`, MCPs no documentados en `06_AGENT_ROLES.md`).
- Completar `.env.example` con `REDIS_CLIENT`.
- PROPIEDADES sigue en diseño/backlog — no es parte de este MVP de autenticación.
