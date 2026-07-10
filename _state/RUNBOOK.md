---
tipo: estado
proyecto: shared
creado: 2026-07-06
actualizado: 2026-07-10
---

# RUNBOOK — Errores conocidos y soluciones

> Registro vivo de errores encontrados durante la operación del sistema Urbania.
> Cuando un agente o el desarrollador encuentra un error y lo resuelve, se agrega una entrada acá.
> Los agentes deben consultar este archivo **antes** de ejecutar comandos de infraestructura
> (ver `_system/AGENT_PREAMBLE.md` §6-bis y §7).

## Cómo usar este archivo

1. **Consultar antes de ejecutar:** si vas a correr un comando de infraestructura, buscá acá si hay
   entradas con tags relacionados.
2. **Registrar después de resolver:** si encontraste un error nuevo y lo solucionaste, agregá una
   entrada con el formato de abajo **en la misma sesión** — no lo dejes para después.
3. **No duplicar:** si un error ya está documentado, seguí la solución existente. Si la solución
   existente no funcionó, agregá una sub-entrada con tu variante y la fecha.

## Formato de entrada

```markdown
### E-NNN: [Título descriptivo]
- **Fecha:** YYYY-MM-DD
- **Agente/Sesión:** [nombre del agente o "manual"]
- **Causa raíz:** [qué pasó y por qué]
- **Síntoma:** [qué se observó — pantalla colgada, timeout, error específico]
- **Solución:** [qué se hizo para resolverlo]
- **Prevención:** [qué cambio de configuración, proceso o documento evita que esto vuelva a pasar]
- **Tags:** #tag1 #tag2
```

---

## Errores activos

### E-001: Agente bloqueado al ejecutar php artisan serve

- **Fecha:** 2026-07-05
- **Agente/Sesión:** urbania (general)
- **Causa raíz:** `Start-Process -NoNewWindow php artisan serve` lanza un proceso servidor HTTP que
  nunca termina (loop infinito esperando requests). La flag `-NoNewWindow` hereda los streams
  stdout/stderr del agente, haciendo que OpenCode espere indefinidamente la salida del proceso hijo.
  Como el servidor nunca emite una señal de "terminé", la sesión queda bloqueada.
- **Síntoma:** Agente no responde, sesión colgada, sin output de error. Es necesario matar el
  proceso manualmente o reiniciar la sesión.
- **Solución:**
  1. **No usar `php artisan serve` desde el agente.** Es un proceso de tipo "Servicio" — ver
     clasificación en `_system/AGENT_PREAMBLE.md` §7.
  2. Para probar requests HTTP sin servidor: usar `php artisan tinker` y ejecutar
     `app()->handle($request)` — esto pasa por todo el stack real de Laravel (middleware, validación,
     controlador) sin necesidad de un servidor web.
  3. Si es necesario un servidor persistente: usar `docker compose up -d`.
  4. En Windows, si no hay alternativa: `Start-Process -WindowStyle Hidden php artisan serve`
     (sin `-NoNewWindow`) — abre una ventana separada y el agente no espera su salida.
- **Prevención:**
  1. Configurado `bash_default_timeout_ms: 60000` en `opencode.json` — cualquier comando que exceda
     60 segundos será matado automáticamente.
  2. Agregado protocolo de clasificación de comandos en `_system/AGENT_PREAMBLE.md` §7.
  3. Los agentes deben consultar este RUNBOOK antes de ejecutar comandos de infraestructura
     (`_system/AGENT_PREAMBLE.md` §6-bis).
- **Tags:** #bloqueo #php-artisan #infraestructura #windows #start-process #timeout

### E-002: Índice de codebase-memory desactualizado o sin código fuente

- **Fecha:** 2026-07-07
- **Agente/Sesión:** urbania (diagnóstico de infraestructura)
- **Causa raíz:** El índice de `codebase-memory` para `URBANIA_NEW` se generó cuando el vault solo contenía documentación (`.md`), antes de que el código fuente existiera en `code/`. El índice solo tiene 534 nodos estructurales (Section, File, Folder) sin edges de CALLS/DATA_FLOWS. No incluye ningún archivo de `code/api/` ni `code/web/`. Adicionalmente, `search_graph` con BM25 no devuelve resultados — el índice de texto completo no se construyó (posiblemente modo `fast`).
- **Síntoma:** `search_graph` devuelve 0 resultados para cualquier query. `trace_path` no encuentra call chains. `get_architecture` solo muestra estructura de carpetas del vault, no del código. Los agentes que intentan usar `codebase-memory` para análisis de código no obtienen datos útiles.
- **Solución:**
  1. Reindexar el proyecto en modo `full`: `codebase-memory index_repository --repo_path "D:\Programacion\URBANIA_NEW" --mode full`
  2. Verificar que el índice ahora incluye archivos bajo `code/api/` y `code/web/`
  3. Confirmar que `search_graph` devuelve resultados para queries como "login controller" o "auth middleware"
- **Prevención:**
  1. El auditor (Check 9) verifica en cada auditoría que `codebase-memory_index_status` muestra nodos de código y que `search_graph` funciona.
  2. `urbania` ejecuta un health check de `codebase-memory` al inicio de cada sesión (ver `06_AGENT_ROLES.md` §1 read-set actualizado).
  3. Después de cada feature completado, se reindexa (agregado al gatillo de release-council).
- **Tags:** #codebase-memory #indice #infraestructura #diagnostico #mcp

### E-003: Agente cambia REDIS_CLIENT o dependencias de sistema sin notificar al usuario

- **Fecha:** 2026-07-07
- **Agente/Sesión:** urbania (diagnóstico de infraestructura)
- **Causa raíz:** El proyecto Urbania configura `REDIS_CLIENT=phpredis` por defecto (`config/database.php` línea 67), que requiere la extensión PHP `ext-redis` (phpredis) compilada en C vía PECL. Cuando esta extensión no está instalada en el entorno, el agente puede detectar la ausencia y — en lugar de notificar al usuario con opciones — cambiar silenciosamente `REDIS_CLIENT=predis` en `.env`. Esto es una decisión de infraestructura que cambia el comportamiento runtime (cliente Redis en C → PHP puro) sin consentimiento del usuario. El patrón se extiende a cualquier dependencia de sistema cuya ausencia el agente "resuelva" por su cuenta sin consultar.
- **Síntoma:** El usuario no sabe que ocurrió el swap. El proyecto funciona pero con un cliente Redis diferente al configurado por defecto (predis ≈30% más lento que phpredis). La decisión queda invisibilizada — el agente no reporta el hallazgo ni pide confirmación.
- **Solución:**
  1. **Nunca cambiar `REDIS_CLIENT` (ni ninguna variable de entorno que afecte el runtime) sin notificar al usuario.** Protocolo ante ausencia de `ext-redis`:
     - Verificar disponibilidad: `php -r "echo extension_loaded('redis') ? 'yes' : 'no';"`
     - Si no está disponible y `REDIS_CLIENT=phpredis`: **notificar al usuario** con las dos opciones:
       - **A)** Instalar `ext-redis` con `pecl install redis` (más rápido, requiere acceso al sistema y que el PHP tenga el tooling de PECL)
       - **B)** Cambiar `REDIS_CLIENT=predis` en `.env` (predis ya está en composer.json, no requiere instalación adicional, pero es más lento)
     - **Esperar la decisión explícita del usuario** antes de continuar con cualquier operación que dependa de Redis.
  2. Si el usuario elige la opción B, después del cambio ejecutar: `php artisan config:clear`
  3. Este protocolo aplica por extensión a cualquier dependencia de sistema ausente: extensión PHP faltante, servicio Docker no corriendo, binario del SO no disponible. La regla es: **detectar → notificar con opciones → esperar decisión → ejecutar.**
- **Prevención:**
  1. Agregado al RUNBOOK como `E-003`. Los agentes consultan el RUNBOOK antes de ejecutar comandos de infraestructura (`AGENT_PREAMBLE.md` §6-bis).
  2. El agente `urbania` debe ejecutar un health check de entorno al inicio de sesión que incluya verificación de extensiones PHP requeridas: `ext-redis`, `ext-pdo_mysql`, `ext-bcmath`, `ext-openssl`.
  3. Principio general reforzado en `AGENT_PREAMBLE.md` §6: cualquier cambio de configuración de runtime (`.env`, `config/*.php`, `docker-compose.yml`) requiere notificación y confirmación explícita del usuario — nunca es silencioso.
- **Tags:** #ext-redis #redis #phpredis #predis #configuracion #infraestructura #env #notificacion #health-check

### E-004: `verifier` no podía re-ejecutar `composer ci` / `pnpm ci` — permiso incompleto

- **Fecha:** 2026-07-09
- **Agente/Sesión:** urbania (reportado por OpenCode durante corrección de bloques)
- **Causa raíz:** El prompt de `verifier` (`.opencode/agents/verifier.md`) instruye textualmente
  "Re-ejecutas los comandos de CI relevantes tú mismo... `composer ci` para API, `pnpm ci` para Web",
  pero su `permission.bash` nunca incluía esos dos comandos literales — solo variantes granulares
  (`composer test*`, `composer stan`, `composer lint`, `pnpm type-check`, `pnpm lint`, `pnpm test*`,
  `pnpm build`). Como ningún patrón coincidía con `composer ci` ni `pnpm ci`, la regla de respaldo
  `"*": deny` los bloqueaba. `verifier` corre en `deepseek/deepseek-v4-flash` (modelo débil), que al
  chocar con el bloqueo reportó genéricamente "no tengo una herramienta de shell" en vez de un error
  de permiso — llevando a un diagnóstico incorrecto de que OpenCode carecía de mecanismo de shell.
- **Síntoma:** El bloque quedaba trabado en `estado: verifying` sin que `verifier` pudiera completar
  su checklist ("CI re-ejecutado personalmente"). El agente intentó rutas alternativas (RCE vía
  Playwright, `COPY TO PROGRAM` en Postgres, etc.) en vez de detectar el permiso faltante.
- **Solución:** Agregado `"composer ci": allow` y `"pnpm ci": allow` al `permission.bash` de
  `verifier.md` — ahora coincide exactamente con los comandos que su propio prompt le exige correr.
  Se confirmó que `api-build` (`"composer *": allow`) y `web-build` (`"pnpm ci": allow` explícito) ya
  tenían el permiso correcto para su parte del pipeline; el enrutamiento
  (`api-orchestrator`/`web-orchestrator` → `api-build`/`web-build` → `verifier`) no tenía ningún
  bug — no parte de este pipeline.
- **Prevención:** Al escribir o editar el prompt de un agente, verificar que cada comando exacto que
  el texto le instruye ejecutar tenga un patrón literal correspondiente en su `permission.bash` — un
  wildcard de un comando distinto (`composer test*`) no cubre otro (`composer ci`).
- **Tags:** #verifier #composer-ci #pnpm-ci #permission #bash #deepseek #diagnostico #falso-positivo

### E-005: `@playwright/test@1.61.1` roto en `code/web` — `test.describe()` no reconocido en proyecto ESM

- **Fecha:** 2026-07-10
- **Agente/Sesión:** urbania (cierre de DoD de PROPIEDADES-B06..B09)
- **Causa raíz:** No confirmada con certeza — hipótesis más probable es una doble instanciación del
  registro interno de `@playwright/test` (`_TestTypeImpl`) en un proyecto con `"type": "module"`
  (`code/web/package.json`): el runner de Playwright parece cargar `playwright.config.ts` por una
  ruta de resolución de módulos distinta a la que usa para cargar los archivos `*.spec.ts`, dejando
  el objeto `test` importado en el spec desconectado del contexto de ejecución activo del runner.
  Descartado: no es un problema de versión de Node — se reprodujo idéntico en Node v25.9.0 y en
  Node v22.23.1 (instalado vía `nvm install 22` específicamente para probar esta hipótesis).
  Descartado: no hay dependencias duplicadas de `@playwright/test` ni de `playwright` en
  `node_modules` (`pnpm list playwright @playwright/test -r` muestra una sola instancia).
  No es específico de los specs nuevos de esta sesión — el spec preexistente
  `e2e/auth/login.spec.ts` (de `AUTH-B06`) falla exactamente igual.
- **Síntoma:** Cualquier invocación de `npx playwright test` (incluyendo `--list`, que ni siquiera
  lanza un navegador) falla en el primer `test.describe()` de cualquier archivo con:
  `Error: Playwright Test did not expect test.describe() to be called here.` seguido de
  `Error: No tests found.` — 0 tests recolectados, 0 archivos.
- **Solución:** Sin resolver. Probado sin éxito, exhaustivamente:
  1. Cambiar la versión de Node activa (`nvm use 22.23.1` en vez de `v25.9.0`) — mismo error.
  2. Downgrade de `@playwright/test` a `1.60.0` — mismo error, incluso con un spec trivial de una
     sola línea (`test("x", () => expect(1+1).toBe(2))`) sin ningún import del proyecto, aliases de
     `@/` ni helpers — descarta que la causa esté en el código de los specs.
  3. Reinstalación forzada (`pnpm install --force`) tras borrar las carpetas de playwright en el
     store de pnpm — mismo error.
  4. Downgrade a la versión **exacta que especifica el `package.json` commiteado**,
     `@playwright/test@1.49.0` (el rango `^1.49.0` del lockfile había resuelto silenciosamente a
     `1.61.1` porque esa era la última 1.x disponible cuando se generó el lockfile — no hay drift
     entre lo commiteado y lo instalado, es una resolución de rango semver legítima pero
     inesperadamente lejana). Con `1.49.0` exacto y su Chromium correspondiente
     (`npx playwright install chromium`), **mismo error idéntico**.
  Con 4 versiones distintas de Playwright (1.49.0, 1.60.0, 1.61.1, y descartada la exploración de
  `1.62.0-alpha-*` por no ser estable) y 2 versiones de Node fallando de forma idéntica en un spec
  mínimo sin ninguna dependencia del proyecto, se descarta con alta confianza que sea una regresión
  de Playwright. La causa más probable pasa a ser algo específico de este entorno/proyecto:
  `"type": "module"` en `code/web/package.json` combinado con Windows + pnpm (symlinks) rompiendo el
  mecanismo interno de Playwright para registrar el runner activo (`TestTypeImpl._currentSuite`)
  antes de que el archivo de test se evalúe. No investigado por quedar fuera de un tiempo
  razonable de sesión: forzar `"type": "commonjs"` temporalmente para playwright.config, o abrir un
  issue upstream con un repro mínimo.
- **Impacto:** Bloquea la verificación visual automatizada (Playwright) exigida por el DoD de
  cualquier bloque `web` — no es específico de `PROPIEDADES`. `PROPIEDADES-B06/B07/B08/B09` se
  cerraron a `verifying` sin este paso, dejándolo documentado como pendiente explícito en cada card
  (ver `_state/BOARD.md`). El spec ya escrito para estos 4 bloques
  (`code/web/e2e/propiedades/propiedades.spec.ts`, hace login real contra el backend en Docker con
  `admin@urbania.test` / `Admin123!` y recorre los criterios de aceptación de las 4 pantallas) queda
  listo para correr en cuanto se resuelva este bloqueo — no requiere cambios de código, solo
  `npx playwright test e2e/propiedades`.
- **Prevención:** Antes de asumir que un fallo de Playwright es del código bajo prueba, correr un
  spec preexistente conocido-bueno (`e2e/auth/login.spec.ts`) para confirmar si el fallo es del
  entorno o del código nuevo — así se descartó rápidamente que fuera un problema de los specs de
  `PROPIEDADES` en este caso.
- **Tags:** #playwright #e2e #esm #test-describe #node-version #propiedades #dod #bloqueo

### E-006: `$wrap` de JsonResource rompía el envelope de POST/PATCH en property-types y property-statuses

- **Fecha:** 2026-07-10
- **Agente/Sesión:** urbania (verificación de contrato alternativa a Playwright, ver `E-005`)
- **Causa raíz:** `PropertyTypeResource` y `PropertyStatusResource`
  (`code/api/src/Properties/Infrastructure/Http/Resources/`) declaraban
  `public static $wrap = 'property_type';` / `'property_status';`. Laravel solo aplica `$wrap`
  cuando el Resource se serializa vía `->response()`/`->toResponse()` (usado en
  `PropertyTypeController::store()` y `::update()`), no cuando el controlador envuelve
  manualmente con `response()->json(['data' => ...])` (usado en `index()` y `show()`). Resultado:
  `GET` devolvía `{data: {...}}` (correcto) pero `POST`/`PATCH` devolvían
  `{property_type: {...}}` / `{property_status: {...}}` — violando el contrato congelado
  `LOCK-PROPIEDADES-01`, que documenta `{data: {...}}` para los tres verbos
  (`api/endpoints/PROPIEDADES.md` líneas 78, 113, 153). Los tests de PHP
  (`PropertyTypeTest`/`PropertyStatusTest`) habían sido escritos contra el bug
  (`$response->json('property_type')`) en vez de contra el contrato documentado, así que nunca lo
  detectaron.
- **Síntoma:** En el frontend real, `useCreatePropertyTypeMutation`/`useUpdatePropertyTypeMutation`
  (y sus equivalentes de status) leen `response.data.nombre` para el toast de éxito. Con
  `response.data === undefined`, eso lanza `TypeError: Cannot read properties of undefined
  (reading 'nombre')` dentro del `onSuccess` de la mutación — un error no capturado en cada
  creación/edición exitosa de un tipo o estado de propiedad personalizado. No se detectó en los
  tests de componente de `PROPIEDADES-B06` porque mockean el hook de API completo (la respuesta
  simulada ya viene con la forma correcta por construcción). Se encontró al escribir un script de
  verificación de contrato contra el backend real
  (`code/web/scripts/verify-propiedades-contract.mjs`) como sustituto de la verificación visual
  Playwright bloqueada por `E-005`.
- **Solución:**
  1. Cambiar `$wrap` a `'data'` en ambos Resources — alinea POST/PATCH con lo que ya hacían
     GET/show y con el contrato congelado.
  2. Corregir las 3 aserciones de test que esperaban el wrap viejo
     (`tests/Feature/Properties/PropertyTypeTest.php` líneas 163, 211;
     `PropertyStatusTest.php` línea 147) de `$response->json('property_type'/'property_status')`
     a `$response->json('data')`.
  3. Re-verificado: `php artisan test --filter=PropertyType` (12 passed) y `--filter=PropertyStatus`
     (6 passed) dentro del contenedor con `-e DB_HOST=postgres -e DB_PORT=5432` (ver nota abajo
     sobre por qué esas variables son necesarias), y el script de contrato completo
     (51/51 checks, antes 49/51).
- **Nota al margen — `php artisan test` dentro del contenedor sin overrides falla:**
  `config/database.php` tiene fallback `env('DB_HOST', 'localhost')` / `env('DB_PORT', '5434')`,
  pensado para correr los tests desde el HOST (donde docker-compose mapea postgres a
  `localhost:5434`). `.env.testing` solo sobreescribe `DB_DATABASE`, así que dentro del contenedor
  (`docker exec`) los tests no encuentran esos DB_HOST/DB_PORT y caen al default incorrecto
  (`localhost:5434`, que desde DENTRO del contenedor no es nada). Workaround usado en esta sesión:
  `docker exec -e DB_HOST=postgres -e DB_PORT=5432 urbania-php php artisan test ...`. No se
  investigó si hay una forma de correr los tests desde el host directamente (requeriría PHP+deps
  instalados fuera de Docker) ni si vale la pena agregar `DB_HOST`/`DB_PORT` a `.env.testing` para
  que `docker exec ... php artisan test` funcione sin overrides — queda como mejora futura.
- **Prevención:** Cuando se agregue una verificación de contrato como la de `E-005`/este bug
  (`code/web/scripts/verify-propiedades-contract.mjs`), correrla tras cualquier cambio en
  Resources/Controllers de `code/api` antes de dar por buena la integración — los tests de
  componente del frontend (con API mockeada) y los tests de PHP (si están mal escritos, como aquí)
  no son suficientes por sí solos para detectar un envelope de respuesta incorrecto.
- **Tags:** #propiedades #json-resource #wrap #contrato #property-types #property-statuses #bug-real #dod

---

## Template para nuevas entradas (copiar y pegar)

```markdown
### E-NNN: [Título descriptivo]
- **Fecha:** YYYY-MM-DD
- **Agente/Sesión:** [nombre]
- **Causa raíz:** [qué pasó]
- **Síntoma:** [qué se observó]
- **Solución:** [qué se hizo]
- **Prevención:** [cómo evitar que vuelva a pasar]
- **Tags:** #tag1 #tag2
```
