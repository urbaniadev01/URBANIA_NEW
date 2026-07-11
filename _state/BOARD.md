---
tipo: estado
proyecto: shared
actualizado: 2026-07-11
---

# BOARD â€” Tablero Ãºnico de estado

> **Este documento es un Ã­ndice/rollup, no una fuente.** Cada fila refleja lo que dice el
> frontmatter de la tarjeta enlazada â€” si alguna vez no coinciden, la tarjeta tiene la razÃ³n y este
> archivo estÃ¡ desactualizado (corregir aquÃ­, nunca al revÃ©s). Ver [[../_system/01_PRINCIPLES#1. Un dato, un dueÃ±o]].
>
> **CÃ³mo usar esto como agente:** tomar el primer bloque en `ready` de arriba hacia abajo â€” el orden
> ya refleja dependencias. Si no hay ninguno en `ready`, detenerse y reportarlo (ver
> [[../_system/00_START_HERE]] Paso 2).

## Features

| Feature | Estado de diseÃ±o | Panorama |
|---|---|---|
| AUTH | SHIPPED | [[../features/AUTH/PANORAMA]] |
| API_BOOTSTRAP | approved | [[../features/API_BOOTSTRAP/PANORAMA]] |
| WEB_BOOTSTRAP | approved | [[../features/WEB_BOOTSTRAP/PANORAMA]] |
| PROPIEDADES | approved | [[../features/PROPIEDADES/PANORAMA]] |
| DIRECTORIO | approved | [[../features/DIRECTORIO/PANORAMA]] |
| DASHBOARD | approved | [[../features/DASHBOARD/PANORAMA]] |
| COBRANZA | approved | [[../features/COBRANZA/PANORAMA]] |
| COMUNICACIONES | approved | [[../features/COMUNICACIONES/PANORAMA]] |
| PORTERIA | approved | [[../features/PORTERIA/PANORAMA]] |
| PORTAL_RESIDENTE | approved | [[../features/PORTAL_RESIDENTE/PANORAMA]] |

> `API_BOOTSTRAP` y `WEB_BOOTSTRAP` no son features de negocio â€” son el setup tÃ©cnico que crea
> `code/api/` y `code/web/` (ver [[../web/adr/ADR-WEB-001-libreria-componentes]]),
> documentados con el mismo mecanismo por consistencia. Cada feature nueva se agrega aquÃ­ al crear
> su `PANORAMA.md` (estado inicial `draft`), siguiendo [[../_system/00_START_HERE]] Paso 4.

## Bloques â€” API_BOOTSTRAP / WEB_BOOTSTRAP

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| API_BOOTSTRAP-B01 | api | **done** | â€” | [[../features/API_BOOTSTRAP/blocks/API_BOOTSTRAP-B01-crear-esqueleto-laravel]] |
| WEB_BOOTSTRAP-B01 | web | **done** | â€” | [[../features/WEB_BOOTSTRAP/blocks/WEB_BOOTSTRAP-B01-instalar-shadcn-tailwind]] |

## Bloques â€” AUTH

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| AUTH-B01 | api | **done** | API_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]] |
| AUTH-B02 | api | **done** | API_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B02-login]] |
| AUTH-B03 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B03-refresh-token]] |
| AUTH-B04 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B04-logout]] |
| AUTH-B05 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B05-rbac-middleware]] |
| AUTH-B06 | web | **done** | AUTH-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B06-pantalla-login]] |
| AUTH-B07 | web | **done** | AUTH-B01 (lock), WEB_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B07-pantalla-registro]] |
| AUTH-B08 | api | **done** | AUTH-B05 | [[../features/AUTH/blocks/AUTH-B08-mfa-enrollment]] |
| AUTH-B09 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B09-recuperacion-password]] |
| AUTH-B10 | web | **done** | AUTH-B08 (lock) | [[../features/AUTH/blocks/AUTH-B10-mfa-verify-web]] |
| AUTH-B11 | web | **done** | AUTH-B08 (lock) | [[../features/AUTH/blocks/AUTH-B11-mfa-enroll-web]] |
| AUTH-B12 | web | **done** | AUTH-B09 (lock) | [[../features/AUTH/blocks/AUTH-B12-forgot-password-web]] |
| AUTH-B13 | web | **done** | AUTH-B09 (lock) | [[../features/AUTH/blocks/AUTH-B13-reset-password-web]] |
| AUTH-B14 | api | **done** | AUTH-B02, AUTH-B08 | [[../features/AUTH/blocks/AUTH-B14-fix-entorno-local-navegador]] |
| AUTH-B15 | api, web | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B15-endpoint-me-dashboard]] |
| AUTH-B16 | web | **done** | â€” | [[../features/AUTH/blocks/AUTH-B16-route-guard-rutas-privadas]] |

## Bloques â€” PROPIEDADES

| ID              | Proyecto(s) | Estado    | Depende de                                | Tarjeta                                                                        |
| --------------- | ----------- | --------- | ----------------------------------------- | ------------------------------------------------------------------------------ |
| PROPIEDADES-B01 | api         | **done**  | API_BOOTSTRAP-B01                         | [[../features/PROPIEDADES/blocks/PROPIEDADES-B01-migraciones-modelos-seeders]] |
| PROPIEDADES-B02 | api         | **done**  | PROPIEDADES-B01                           | [[../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]              |
| PROPIEDADES-B03 | api         | **done**  | PROPIEDADES-B01                           | [[../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]     |
| PROPIEDADES-B04 | api         | **done**  | PROPIEDADES-B01                           | [[../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]]               |
| PROPIEDADES-B05 | api         | **done**  | PROPIEDADES-B01                           | [[../features/PROPIEDADES/blocks/PROPIEDADES-B05-coeficientes-tree]]           |
| PROPIEDADES-B06 | web         | **done** | PROPIEDADES-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B06-pantallas-catalogos]]         |
| PROPIEDADES-B07 | web         | **done** | PROPIEDADES-B03 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B07-pantallas-condominios]]       |
| PROPIEDADES-B08 | web         | **done** | PROPIEDADES-B04 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B08-pantalla-unidades]]           |
| PROPIEDADES-B09 | web         | **done** | PROPIEDADES-B05 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B09-pantalla-coeficientes]]       |

## Bloques â€” DIRECTORIO

| ID             | Proyecto(s) | Estado      | Depende de                               | Tarjeta                                                                        |
| -------------- | ----------- | ----------- | ---------------------------------------- | ------------------------------------------------------------------------------ |
| DIRECTORIO-B01 | api         | **done** | AUTH-B01, PROPIEDADES-B01                | [[../features/DIRECTORIO/blocks/DIRECTORIO-B01-fundacion-contactos-ocupantes]] |
| DIRECTORIO-B02 | api         | **done** | DIRECTORIO-B01                           | [[../features/DIRECTORIO/blocks/DIRECTORIO-B02-crud-tipos-ocupante]]           |
| DIRECTORIO-B03 | api         | **done** | DIRECTORIO-B01                           | [[../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]                |
| DIRECTORIO-B04 | api         | **done** | DIRECTORIO-B01                           | [[../features/DIRECTORIO/blocks/DIRECTORIO-B04-asignacion-ocupantes]]          |
| DIRECTORIO-B05 | web         | **verifying** | DIRECTORIO-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/DIRECTORIO/blocks/DIRECTORIO-B05-pantalla-tipos-ocupante]]       |
| DIRECTORIO-B06 | web         | **verifying** | DIRECTORIO-B03 (lock), WEB_BOOTSTRAP-B01 | [[../features/DIRECTORIO/blocks/DIRECTORIO-B06-pantalla-directorio-contactos]] |
| DIRECTORIO-B07 | web         | **verifying** | DIRECTORIO-B04 (lock), WEB_BOOTSTRAP-B01 | [[../features/DIRECTORIO/blocks/DIRECTORIO-B07-pantalla-asignacion-ocupantes]] |

> **Nota (2026-07-10) â€” cierre de DoD de `DIRECTORIO-B01`:** `composer ci` limpio en `code/api`
> (Pint, PHPStan 0 errores, 230/230 tests incluyendo 25 nuevos: `DirectorioMigrationTest` y
> `DirectorioModelTest`). Reversibilidad de las 3 migraciones (`migrate` â†’ `migrate:rollback --step=3`
> â†’ `migrate`) verificada contra la base de datos real (Docker), no solo la de test â€” evidencia
> pegada en la tarjeta. Backfill de `contacts.organization_id` confirmado (0 filas NULL) y
> `OccupantTypeSeeder` confirmado (4 registros, `organization_id`/`created_by` NULL). El guard clause
> de `PROPIEDADES-B04` fue reemplazado por la consulta real (ver nota arriba). Tarjeta pasa a
> `estado: verifying` â€” dado `verificacion_critica: true`, falta el `verify-council` independiente
> antes de `done` (no lo ejecuta el agente implementador, ver `CLAUDE.md`).
>
> **Actualizado (2026-07-11) â€” `verify-council` corrido, tarjeta pasa a `done`:** 3 revisores en
> paralelo (`sec-reviewer`, `perf-reviewer`, `code-reviewer`) encontraron 3 hallazgos reales, los 3
> corregidos: (1) el conteo de tests pegado en la evidencia era incorrecto (25 vs. 20 reales,
> corregido en el texto), (2) `down()` de la migraciÃ³n correctiva de `contacts` no contemplaba
> contactos con `user_id IS NULL` (el propio caso que R-DIR-02 habilita) â€” ahora falla con un error
> explÃ­cito en vez de una violaciÃ³n de constraint crÃ­ptica, con test de regresiÃ³n, (3)
> `property_occupants` no tenÃ­a Ã­ndice lÃ­der en `property_id`/`occupant_type_id` â€” agregado antes de
> que la migraciÃ³n corriera en producciÃ³n. Re-verificaciÃ³n completa: Pint limpio, PHPStan 0 errores,
> 232/232 tests (230 baseline + 2 nuevos de esta verificaciÃ³n). Detalle completo en la secciÃ³n
> "VerificaciÃ³n" de la tarjeta. Usuario autorizÃ³ explÃ­citamente el pase a `done`. Destraba
> `DIRECTORIO-B02/B03/B04` (ahora `ready`) y `COBRANZA-B01` (ahora `ready`, ver nota en su secciÃ³n).
>
> **Efecto colateral encontrado y corregido:** al agregar un segundo archivo de test que corre
> `migrate:fresh` directamente (`DirectorioMigrationTest`, sin `RefreshDatabase`), `php artisan test
> --parallel` empezÃ³ a repartir ese archivo y el ya existente `PropertiesMigrationTest` (mismo patrÃ³n,
> de `PROPIEDADES-B01`) a procesos distintos que corrÃ­an migraciones simultÃ¡neas contra la misma base
> fÃ­sica â€” antes invisible porque solo existÃ­a un archivo asÃ­. Corregido con un helper nuevo en
> `tests/Pest.php` (`useIsolatedMigrationTestDatabase()`) que aÃ­sla cada suite de este tipo en su
> propia base de datos por proceso paralelo. Detalle completo en la secciÃ³n Notas de la tarjeta.

> **Actualizado (2026-07-11) — verificación visual real completada para DIRECTORIO-B05/B06/B07**
> con un MCP de navegador (Playwright) recién habilitado en esta sesión de Claude Code, desbloqueando
> lo que antes dependía de revisión manual del usuario (`RUNBOOK.md#E-005`, runner de Playwright
> roto en este repo — sigue sin resolverse, pero ya no es un bloqueante gracias al MCP). Las 3
> pantallas se ejercitaron de punta a punta contra el backend real, sin mocks: catálogo de tipos de
> ocupante, alta de contacto, y el ciclo completo de asignar/desasignar un ocupante a una unidad.
> Dos bugs reales encontrados y corregidos durante la verificación — detalle completo en
> `RUNBOOK.md#E-009` (API: `ContactListResource` omitía `user_id`, rompiendo el badge "Con
> cuenta"/"Sin cuenta" para todo contacto) y `RUNBOOK.md#E-010` (Web: `tryRefresh()` sin
> deduplicación, causaba `500` intermitente en `/auth/refresh` por `jti` duplicado — probable causa
> de fondo de `E-007`). Ambos con test de regresión y suite completa re-verificada (PHP 276/276,
> frontend 161/161, `tsc --noEmit` limpio). Evidencia y detalle por pantalla en la sección Notas de
> cada tarjeta (`DIRECTORIO-B05/B06/B07`). Las 3 cards permanecen en `estado: verifying` — la
> transición a `done` sigue siendo decisión del usuario (ver `CLAUDE.md`), no cambiada acá.

## Bloques â€” DASHBOARD

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| DASHBOARD-B01 | web | **done** | WEB_BOOTSTRAP-B01 | [[../features/DASHBOARD/blocks/DASHBOARD-B01-widget-registry-core]] |
| DASHBOARD-B02 | web | **done** | DASHBOARD-B01, PROPIEDADES-B03 (lock), PROPIEDADES-B04 (lock), PROPIEDADES-B05 (lock) | [[../features/DASHBOARD/blocks/DASHBOARD-B02-propiedades-widgets]] |
| DASHBOARD-B03 | web | **done** | DASHBOARD-B01 | [[../features/DASHBOARD/blocks/DASHBOARD-B03-core-widgets-placeholders]] |

> **Nota (2026-07-07):** DiagnÃ³stico de infraestructura: `codebase-memory` estÃ¡ instalado pero su
> Ã­ndice actual (534 nodos) solo cubre la documentaciÃ³n del vault â€” no incluye el cÃ³digo fuente en
> `code/api/` ni `code/web/`. `search_graph` no funciona. Pendiente: reindexar en modo `full` para
> que los agentes puedan usar anÃ¡lisis de cÃ³digo real. Ver `_state/RUNBOOK.md#E-002`.
>
> **Nota (2026-07-08, resuelta 2026-07-10):** `PROPIEDADES-B04` (CRUD de unidades) implementaba un
> guard clause temporal para la regla "no eliminar unidad con ocupantes activos" (R-03), porque
> `property_occupants` pertenecÃ­a a una feature que todavÃ­a no existÃ­a. **Resuelto por
> `DIRECTORIO-B01`** (ver mÃ¡s abajo, `verifying`): la tabla `property_occupants` ya existe y
> `PropertyController::destroy` fue actualizado para consultarla directamente â€” el guard clause y su
> `@todo` fueron eliminados. Ver [[../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades#Notas]]
> y [[../features/DIRECTORIO/blocks/DIRECTORIO-B01-fundacion-contactos-ocupantes]].
>
> **Nota (2026-07-08, ejecutada 2026-07-10):** `DIRECTORIO-B01` tambiÃ©n corrigiÃ³ `contacts` (de
> `AUTH-B01`, ya `SHIPPED`): `user_id` pasÃ³ a nullable y se agregÃ³ `organization_id` propio, porque
> el diseÃ±o aprobado de AUTH ya prometÃ­a "un contact puede existir sin user" (ADR-001) pero la
> migraciÃ³n real lo implementÃ³ como NOT NULL. IncluyÃ³ un parche de una lÃ­nea en
> `RegisterUserUseCase` con test de regresiÃ³n (`RegisterTest`, sin modificar, sigue pasando). Backfill
> verificado sobre datos reales â€” 0 filas con `organization_id NULL` tras la migraciÃ³n. Ver evidencia
> completa en [[../features/DIRECTORIO/blocks/DIRECTORIO-B01-fundacion-contactos-ocupantes]].

> **Nota (2026-07-10) â€” cierre de DoD de `PROPIEDADES-B06/B07/B08/B09`:** Completado en esta sesiÃ³n:
> `pnpm run ci` limpio en `code/web` (type-check, lint, 126 tests incluyendo 50 nuevos de
> componente para las 4 pantallas, build) â€” evidencia pegada en cada card; los 4 locks
> (`LOCK-PROPIEDADES-01/02/03/04`) confirmados vigentes y respetados exactamente por la integraciÃ³n;
> housekeeping (`start-dev.bat`, `verify-b03.bat` borrados). La verificaciÃ³n visual real
> (Playwright) quedÃ³ bloqueada â€” `@playwright/test` estÃ¡ roto en este entorno (probado en 1.49.0
> exacto committeado, 1.60.0 y 1.61.1, en Node v22 y v25, ver `_state/RUNBOOK.md#E-005`) â€” el spec
> ya escrito (`code/web/e2e/propiedades/propiedades.spec.ts`, sin mocks, login real) queda listo
> para correr en cuanto se resuelva. **Como sustituto**, se escribiÃ³ y corriÃ³
> `code/web/scripts/verify-propiedades-contract.mjs` (login real contra el backend, sin mocks,
> 51/51 checks) que verifica el contrato APIâ†”Web exacto de los 4 locks â€” encontrÃ³ y permitiÃ³
> corregir un bug real: `POST`/`PATCH` de `property-types`/`property-statuses` violaban
> `LOCK-PROPIEDADES-01` (envelope `{property_type: ...}` en vez de `{data: ...}`, rompÃ­a el toast de
> Ã©xito en el navegador con un `TypeError`). Ver `_state/RUNBOOK.md#E-006` y
> `PROPIEDADES-B02#Notas` (bloque productor, ya `done`, donde vivÃ­a el bug â€” corregido con evidencia
> de tests PHP re-verificados). Las 4 cards pasaron a `estado: verifying` con la verificaciÃ³n visual
> como Ãºnico pendiente explÃ­cito, documentado en su propia secciÃ³n Evidencia â€” la transiciÃ³n a
> `done` la hace el usuario,
> no el agente que implementa (ver `CLAUDE.md`).

> **Nota (2026-07-10) â€” pasada grande de rediseÃ±o visual (`code/web`), fuera del flujo doc-first:**
> Por decisiÃ³n explÃ­cita del usuario, se ejecutÃ³ una actualizaciÃ³n visual grande directamente en
> cÃ³digo â€” sin pasar por el proceso normal de bloque/PANORAMA.md por pantalla â€” con el registro en
> documentaciÃ³n hecho reciÃ©n al terminar (esta nota + `web/WEB_VISUAL_STANDARDS.md`). Es una
> excepciÃ³n puntual autorizada en conversaciÃ³n, no un cambio al protocolo (ver `CLAUDE.md`: Claude
> propone/ejecuta, el humano aprueba).
>
> **Hallazgo relevante para `PROPIEDADES-B06/B07/B08/B09` (nota anterior, arriba):** el router de
> `code/web` no envolvÃ­a ninguna ruta privada en `DashboardShell` salvo `DashboardPage` â€” las 4
> pantallas de PROPIEDADES no tenÃ­an sidebar/header en absoluto. Corregido con un nuevo
> `src/app/AppLayout.tsx` que instancia `DashboardShell` una Ãºnica vez para todas las rutas
> privadas. Esto no cambia el pendiente de verificaciÃ³n visual real (Playwright) documentado en la
> nota anterior â€” sigue bloqueado por lo mismo (`_state/RUNBOOK.md#E-005`) â€” pero elimina una causa
> real de inconsistencia visual que existÃ­a independientemente de ese bloqueo.
>
> **Resto de los cambios** (componentes compartidos `PageHeader`/`EmptyState`/`LoadingState`/
> `RouteSuspenseFallback`, `AuthLayout` split-screen en las 6 pantallas de auth, tokens semÃ¡nticos
> `success`/`warning`/`info`/`accent-brand` reemplazando clases Tailwind crudas dispersas, restyle
> de `DashboardShell`): detalle completo en `web/WEB_VISUAL_STANDARDS.md` Â§1.1-Â§1.3, que es ahora la
> fuente de verdad durable de este cambio, no esta nota.
>
> **VerificaciÃ³n:** `pnpm run ci` en `code/web` â€” type-check y build limpios, 126/126 tests
> unitarios pasando (mismo baseline que antes de esta pasada, sin regresiones). El Ãºnico error de
> lint (`e2e/propiedades/propiedades.spec.ts`, variable sin usar) es preexistente y ajeno a este
> cambio. **No hubo verificaciÃ³n visual automatizada** (Playwright roto, ver arriba) ni por MCP de
> browser (no disponible en el entorno de esta sesiÃ³n) â€” el usuario revisÃ³ visualmente el resultado
> de forma manual antes de aprobar el cierre de esta pasada.
>
> **Alcance no cubierto, explÃ­citamente fuera de esta pasada:** no se agregÃ³ menÃº de
> usuario/logout en el header (no existÃ­a antes; se detectÃ³ pero no se agregÃ³, por ser
> funcionalidad nueva y no restyle). No se retrofiteÃ³ Â§7 "UI/UX" en `AUTH/PANORAMA.md` ni
> `PROPIEDADES/PANORAMA.md` (quedan sin esa secciÃ³n, por decisiÃ³n de alcance forward-only tomada en
> la sesiÃ³n que introdujo Â§7 â€” ver `_system/templates/FEATURE_PANORAMA.md`).

> **Nota (2026-07-10) â€” segunda mitad de la pasada de rediseÃ±o visual (`code/web`): comportamientos
> globales + baseline MVP, fuera del flujo doc-first:** Misma excepciÃ³n autorizada que la nota
> anterior (decisiÃ³n explÃ­cita del usuario en conversaciÃ³n, no un cambio de protocolo â€” ver
> `CLAUDE.md`). Cierra los dos huecos que la pasada anterior habÃ­a dejado explÃ­citamente pendientes
> (user menu/logout) y agrega la capa de comportamiento transversal que hasta ahora cada pantalla
> resolvÃ­a por su cuenta.
>
> **Colores de marca:** `--primary` y `--accent-brand` (`code/web/src/index.css`) se recalibraron a
> partir de `logo.jpg` (azul marino `#1b3a5c`, verde `#3e8e41`) â€” antes eran azul/pÃºrpura genÃ©ricos.
> `--success` se mantiene como token de estado independiente. Valores estimados desde un JPG
> comprimido, a refinar si en el futuro hay un manual de marca formal.
>
> **Modo oscuro:** implementado â€” `ThemeProvider` propio (`src/components/theme-provider.tsx`,
> receta oficial de shadcn/ui para Vite, sin `next-themes` por ser Next-only) + `ModeToggle` en el
> header. Persistido en `localStorage`, default = preferencia del sistema. Bloque `.dark` nuevo en
> `index.css`.
>
> **MenÃº de usuario / logout:** `UserMenu` (`src/components/user-menu.tsx`) en el header de
> `DashboardShell` â€” no existÃ­a antes de esta pasada. Llama a `POST /api/v1/auth/logout`
> (`useLogoutMutation`, `features/auth/api/logout.ts`, nuevo) y limpia el store local sin importar la
> respuesta del servidor.
>
> **PÃ¡ginas de error (mÃ­nimas, MVP):** `NotFoundPage` (ruta catch-all `*`) y `ErrorBoundary`
> genÃ©rico envolviendo `App.tsx` â€” ninguno existÃ­a antes.
>
> **Logo real:** `code/web/public/logo.jpg` reemplaza el texto/Ã­cono placeholder en `DashboardShell`
> y `AuthLayout`. Es el JPG tal cual (fondo blanco, sin recortar) â€” no habÃ­a herramienta de ediciÃ³n
> de imagen disponible en el entorno; una versiÃ³n recortada/transparente (PNG o SVG) es un ajuste
> cosmÃ©tico pendiente, no bloqueante.
>
> **DocumentaciÃ³n:** `web/WEB_VISUAL_STANDARDS.md` Â§1.2 (colores actualizados) y Â§6 nuevo
> "Comportamientos globales" (modo oscuro, confirmaciÃ³n de acciones destructivas, toasts, loading,
> tablas, formularios, responsive, iconografÃ­a, user menu, pÃ¡ginas de error; command palette/i18n
> explÃ­citamente fuera de esta pasada) â€” es ahora la fuente de verdad durable de este cambio, no
> esta nota.
>
> **Referencia externa:** se consultÃ³ `satnaing/shadcn-admin` (mismo stack: Vite+React+TS+shadcn+
> Tailwind) como referencia de patrones (dark mode, user menu, error pages) â€” no se instalÃ³ ni se
> clonÃ³, solo se adaptaron decisiones de UX ya validadas a los componentes propios existentes.
>
> **VerificaciÃ³n:** `pnpm run type-check`, `pnpm run test` (126/126) y `pnpm run build` limpios en
> `code/web`. `pnpm run lint` tiene el mismo error preexistente y ajeno a este cambio ya documentado
> en la nota anterior (`e2e/propiedades/propiedades.spec.ts`, variable sin usar) â€” bloquea el script
> `pnpm run ci` encadenado, por eso se verificÃ³ cada paso por separado. Se encontraron y corrigieron
> dos incompatibilidades del entorno de test con jsdom durante esta verificaciÃ³n: `localStorage`
> puede lanzar bajo el bug de Node â‰¥22 con `--localstorage-file` (ver `_state/RUNBOOK.md#E-005`,
> mismo Node afectado que rompe Playwright) â€” se envolviÃ³ en try/catch con fallback silencioso; y
> jsdom no implementa `window.matchMedia` â€” se agregÃ³ un mock en `src/test-setup.ts`, mismo patrÃ³n
> que el mock existente de `IntersectionObserver`. **No hubo verificaciÃ³n visual automatizada**
> (Playwright sigue roto, mismo bloqueo que la pasada anterior) â€” pendiente de revisiÃ³n manual del
> usuario antes de cerrar.
>
> **Bug encontrado en revisiÃ³n manual del usuario (mismo dÃ­a) â€” corregido:** `UserMenu` rompÃ­a el
> login para todo usuario cuyo `GET /auth/me` devuelve `name: null` (usuario sin contacto asociado â€”
> caso documentado en `api/endpoints/AUTH.md`, aunque `AuthUser.name` lo tipa como `string` no
> nulable). `initials()` llamaba `.trim()` sobre ese `null`, el `ErrorBoundary` nuevo lo capturaba y
> mostraba su pantalla de error en vez del dashboard â€” apareciÃ³ como "no me deja entrar al
> dashboard" tras loguearse. Corregido en `src/components/user-menu.tsx`: `initials()` ahora acepta
> `name: string | null | undefined` con fallback a la inicial del email; el label del dropdown
> tambiÃ©n cae a `user.email` cuando no hay `name`. Sin el `ErrorBoundary` el fallo hubiera sido una
> pantalla en blanco sin mensaje â€” queda anotado para que quede claro que el `ErrorBoundary` cumpliÃ³
> su funciÃ³n (mostrar algo en vez de una pantalla en blanco), no que introdujo el bug. Reverificado:
> `pnpm run test` 126/126 y `pnpm run type-check` limpios tras el fix.

> **Nota (2026-07-10) â€” tercera pasada de diseÃ±o en vivo (`code/web`), fuera del flujo doc-first:**
> Misma excepciÃ³n autorizada que las dos notas anteriores (decisiÃ³n explÃ­cita del usuario en
> conversaciÃ³n, no un cambio de protocolo â€” ver `CLAUDE.md`). SesiÃ³n de iteraciÃ³n rÃ¡pida
> pantalla-por-pantalla (login primero, despuÃ©s header/sidebar del dashboard), con el registro en
> documentaciÃ³n hecho reciÃ©n al cerrar la sesiÃ³n, como en las pasadas anteriores.
>
> **`LoginPage` â€” layout propio, fuera del patrÃ³n `AuthLayout`:** fondo de imagen a pantalla
> completa (`code/web/public/background.png`, movido acÃ¡ desde el vault por pedido del usuario) +
> panel central "glass" (logo flotante + formulario). Tokens nuevos en `index.css`/
> `tailwind.config.ts`: `--brand-cta` (+ hover/active/foreground), `--surface-glass` (+
> border/foreground), `--input-accent-bg`/`--input-accent-border` â€” documentados en
> `web/WEB_VISUAL_STANDARDS.md` Â§1.2. TipografÃ­a `Space Grotesk` (Google Fonts) agregada como
> `font-display` para tÃ­tulos, sin reemplazar `Inter` como fuente de cuerpo. Es la Ãºnica de las 6
> pantallas de auth con este tratamiento â€” detalle de la divergencia (incluye el logo distinto,
> `logo.png` vs `logo.jpg`) en `web/WEB_VISUAL_STANDARDS.md` Â§1.3.
>
> **Dashboard â€” header y sidebar:** `CommandMenu` (bÃºsqueda de features, `Cmd/Ctrl+K`, nuevo
> componente `command.tsx` sobre `cmdk` â€” dependencia nueva, antes no estaba en `package.json`) y
> `ThemeCustomizer` (radio de bordes / color de acento / escala de UI, persistido en `localStorage`,
> independiente de `ModeToggle`) agregados al header de `DashboardShell`. El sidebar tenÃ­a 5 Ã­tems
> muertos (`Unidades`, `Coeficientes`, `Directorio`, `Cobranza` en `features/dashboard/widgets/index.ts`
> apuntaban a rutas sin implementar; `features/propiedades/dashboard.ts` registraba ademÃ¡s un
> "Condominios" duplicado con typo, `/condominiums` en vez de `/condominios`) â€” eliminados. Se agregÃ³
> `SidebarNavItem.icon?: LucideIcon`, visible siempre (antes, colapsado mostraba la inicial del
> label). Nuevo grupo "AdministraciÃ³n" (`Tipos de propiedad`, `Estados de propiedad` â€” pantallas que
> ya existÃ­an como ruta pero no estaban en ningÃºn menÃº) gateado por el permiso `admin.access` (mismo
> permiso que usa el backend para `admin`/`manager`, ver `RbacDemoSeeder`) â€” corrige que
> `CommandMenu` mostraba esas pantallas a cualquier usuario autenticado sin chequear permiso;
> `CommandMenu` ahora reusa `getVisibleSidebar(user)` en vez de una lista hardcodeada. Color del
> Ã­tem activo del sidebar: `accent-brand` (fijo) â†’ `primary` (sigue el acento de `ThemeCustomizer`).
> Logo del sidebar: `logo.jpg` â†’ `logo.png` (mismo archivo que `LoginPage`, ver divergencia arriba).
> Saludo del dashboard: `"Buenos dÃ­as/tardes/noches, {nombre}"` â†’ `"Hola, {nombre}"` fijo.
>
> **Dos bugs reales encontrados y corregidos** (no eran restyle, eran funcionalidad rota):
> 1. *SesiÃ³n se perdÃ­a al refrescar la pÃ¡gina* â€” `RequireAuth.tsx` (`AUTH-B16`, ya `done`) leÃ­a el
>    `accessToken` en memoria (Zustand sin `persist`, por diseÃ±o) de forma sÃ­ncrona al montar y
>    redirigÃ­a a `/login` antes de intentar `POST /api/v1/auth/refresh` con la cookie httpOnly
>    `refresh_token` que sÃ­ sobrevive al F5. Fix: intenta el refresh una vez (mostrando
>    `LoadingState`) antes de decidir si redirige. Post-`done` fix sobre esa card â€” no reabre la
>    card, se documenta acÃ¡.
> 2. *`user.name` llegaba `null`* ("Buenas noches, null" en el saludo, texto cortado en el header) â€”
>    `MeResource`/`UserResource` (API) arman el nombre desde `user.contact?->nombre`, y
>    `DemoUserSeeder.php` creaba el `User` de `admin@urbania.test` sin su `Contact` asociado (el flujo
>    normal de registro sÃ­ lo crea, `RegisterUserUseCase.php`). Corregido en el seeder + `Contact`
>    creado directo en la base de desarrollo actual vÃ­a tinker.
>
> **Credenciales de desarrollo disponibles** (sin cambios de contraseÃ±a en esta pasada, solo
> confirmadas): `admin@urbania.test` / `Admin123!` (rol `admin`, scope organization â€” ve el grupo
> "AdministraciÃ³n"); `test+mfa@urbania.test` / `Secret1pass` (MFA pre-habilitado, TOTP
> `JBSWY3DPEHPK3PXP`, cÃ³digos de recuperaciÃ³n `RECV0-ERY01`â€¦`RECV0-ERY08`, sin rol RBAC asignado). Los
> roles `manager`/`resident` existen seedeados (`RbacDemoSeeder`) pero **sin usuario demo asignado**
> â€” pendiente si se necesita probar esas vistas.
>
> **VerificaciÃ³n:** `npx tsc --noEmit` y `npx eslint` limpios en cada archivo tocado (no se corriÃ³
> `pnpm run ci` completo esta vez). Mismo bloqueo de siempre para verificaciÃ³n visual automatizada
> (`_state/RUNBOOK.md#E-005`, Playwright roto en este entorno) â€” el usuario revisÃ³ cada cambio
> visualmente en el navegador de forma manual e iterativa durante la sesiÃ³n (ida y vuelta en vivo),
> no como un review posterior Ãºnico.
>
> **DocumentaciÃ³n:** `web/WEB_VISUAL_STANDARDS.md` â€” tabla de componentes (`Command`), tabla de
> tokens (nota de personalizaciÃ³n en runtime), tabla de layouts compartidos (`CommandMenu`,
> `ThemeCustomizer`, divergencia de `LoginPage`) y Â§6 (command palette y personalizaciÃ³n visual ya no
> figuran como "fuera de esta pasada"; saludo fijo documentado) â€” es la fuente de verdad durable de
> este cambio, no esta nota.

## Bloques â€” COBRANZA

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| COBRANZA-B01 | api | **ready** | PROPIEDADES-B01, DIRECTORIO-B01 | [[../features/COBRANZA/blocks/COBRANZA-B01-migraciones-modelos-seeders]] |
| COBRANZA-B02 | api | **backlog** | COBRANZA-B01 | [[../features/COBRANZA/blocks/COBRANZA-B02-crud-conceptos-cobro]] |
| COBRANZA-B03 | api | **backlog** | COBRANZA-B02 | [[../features/COBRANZA/blocks/COBRANZA-B03-periodos-facturacion]] |
| COBRANZA-B04 | api | **backlog** | COBRANZA-B03 | [[../features/COBRANZA/blocks/COBRANZA-B04-cuentas-cobro]] |
| COBRANZA-B05 | api | **backlog** | COBRANZA-B04 | [[../features/COBRANZA/blocks/COBRANZA-B05-pagos]] |
| COBRANZA-B06 | api | **backlog** | COBRANZA-B05 | [[../features/COBRANZA/blocks/COBRANZA-B06-paz-y-salvo]] |
| COBRANZA-B07 | web | **backlog** | COBRANZA-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B07-pantallas-conceptos-cobro]] |
| COBRANZA-B08 | web | **backlog** | COBRANZA-B03 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B08-pantallas-periodos-facturacion]] |
| COBRANZA-B09 | web | **backlog** | COBRANZA-B04 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B09-pantalla-cuentas-cobro]] |
| COBRANZA-B10 | web | **backlog** | COBRANZA-B05 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B10-pantalla-pagos]] |
| COBRANZA-B11 | web | **backlog** | COBRANZA-B06 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B11-pantalla-paz-y-salvo]] |

> **Actualizado (2026-07-11):** `COBRANZA-B01` pasa a `ready` — `DIRECTORIO-B01` llegó a `done`
> (verify-council OK, ver su tarjeta). Prerrequisito de diseño ya resuelto:
> [[../shared/adr/ADR-002-lectura-cross-context-modulo-monolito]] (`Aceptada`). `COBRANZA-B03` y `COBRANZA-B05` llevan
> `verificacion_critica: true` (cÃ¡lculo financiero de facturaciÃ³n y locking de pagos,
> respectivamente), igual que `COBRANZA-B11` (Ãºltimo bloque del feature). Ver
> [[../features/COBRANZA/BLOCKS]] para el detalle de la cadena y la acciÃ³n pendiente cross-feature
> con `DASHBOARD`.

## Bloques â€” COMUNICACIONES

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| COMUNICACIONES-B01 | api | **ready** | AUTH-B01, PROPIEDADES-B03 | [[../features/COMUNICACIONES/blocks/COMUNICACIONES-B01-migracion-modelo-crud-anuncios]] |
| COMUNICACIONES-B02 | web | **backlog** | COMUNICACIONES-B01 (lock), WEB_BOOTSTRAP-B01 | [[../features/COMUNICACIONES/blocks/COMUNICACIONES-B02-pantalla-anuncios]] |

> `COMUNICACIONES-B01` arranca en `ready` â€” sus dos dependencias (`AUTH-B01`, `PROPIEDADES-B03`) ya
> estÃ¡n `done`. Feature independiente de `DIRECTORIO`/`COBRANZA`. `COMUNICACIONES-B02` lleva
> `verificacion_critica: true` por ser el Ãºltimo bloque de la feature (mismo criterio que
> `COBRANZA-B11`), no por riesgo tÃ©cnico. Ver [[../features/COMUNICACIONES/BLOCKS]].

## Bloques â€” PORTERIA

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| PORTERIA-B01 | api | **ready** | AUTH-B05, PROPIEDADES-B03, PROPIEDADES-B04 | [[../features/PORTERIA/blocks/PORTERIA-B01-migraciones-modelos-crud-vigilante]] |
| PORTERIA-B02 | web | **backlog** | PORTERIA-B01 (lock), WEB_BOOTSTRAP-B01 | [[../features/PORTERIA/blocks/PORTERIA-B02-pantalla-visitantes]] |
| PORTERIA-B03 | web | **backlog** | PORTERIA-B01 (lock), DIRECTORIO-B04 (lock), WEB_BOOTSTRAP-B01 | [[../features/PORTERIA/blocks/PORTERIA-B03-pantalla-correspondencia]] |

> `PORTERIA-B01` arranca en `ready` â€” sus tres dependencias ya estÃ¡n `done`. `PORTERIA-B02`
> (Visitantes) solo depende del lock de `PORTERIA-B01`. `PORTERIA-B03` (Correspondencia) ademÃ¡s
> depende del lock de `DIRECTORIO-B04` (hoy `backlog`) para el selector de destinatario del
> paquete â€” queda bloqueado por esa dependencia cross-feature aunque `PORTERIA-B01` estÃ© `done`.
> `PORTERIA-B02` y `PORTERIA-B03` llevan ambos `verificacion_critica: true` por ser candidatos a
> cerrar la feature (el orden real de ejecuciÃ³n entre ellos no estÃ¡ fijado) â€” ver
> [[../features/PORTERIA/BLOCKS]].

## Bloques — PORTAL_RESIDENTE

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| PORTAL_RESIDENTE-B01 | web | **backlog** | DASHBOARD-B02, COBRANZA-B04 (lock), COMUNICACIONES-B01 (lock), DIRECTORIO-B03 (lock) | [[../features/PORTAL_RESIDENTE/blocks/PORTAL_RESIDENTE-B01-widgets-saldo-avisos]] |

> Único bloque de la feature — 2 widgets nuevos en el Dashboard existente, cero endpoints/tablas
> nuevas. Queda en `backlog`: depende de 3 locks cross-feature, de los cuales solo
> `COMUNICACIONES-B01` existe hoy (`ready`). `COBRANZA-B04` y `DIRECTORIO-B03` siguen en `backlog`
> en sus propias features. Lleva `verificacion_critica: true` por ser el único/último bloque de la
> feature. Ver [[../features/PORTAL_RESIDENTE/BLOCKS]].

## Cómo se actualiza este tablero

Lo actualiza el rol orquestador (ver [[../_system/06_AGENT_ROLES]]) cada vez que una tarjeta cambia
de estado â€” es una ediciÃ³n mecÃ¡nica de una fila, nunca una reinterpretaciÃ³n del progreso.

