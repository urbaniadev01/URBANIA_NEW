---
tipo: estado
proyecto: shared
creado: 2026-07-06
actualizado: 2026-07-10
---

# RUNBOOK â€” Errores conocidos y soluciones

> Registro vivo de errores encontrados durante la operaciÃ³n del sistema Urbania.
> Cuando un agente o el desarrollador encuentra un error y lo resuelve, se agrega una entrada acÃ¡.
> Los agentes deben consultar este archivo **antes** de ejecutar comandos de infraestructura
> (ver `_system/AGENT_PREAMBLE.md` Â§6-bis y Â§7).

## CÃ³mo usar este archivo

1. **Consultar antes de ejecutar:** si vas a correr un comando de infraestructura, buscÃ¡ acÃ¡ si hay
   entradas con tags relacionados.
2. **Registrar despuÃ©s de resolver:** si encontraste un error nuevo y lo solucionaste, agregÃ¡ una
   entrada con el formato de abajo **en la misma sesiÃ³n** â€” no lo dejes para despuÃ©s.
3. **No duplicar:** si un error ya estÃ¡ documentado, seguÃ­ la soluciÃ³n existente. Si la soluciÃ³n
   existente no funcionÃ³, agregÃ¡ una sub-entrada con tu variante y la fecha.

## Formato de entrada

```markdown
### E-NNN: [TÃ­tulo descriptivo]
- **Fecha:** YYYY-MM-DD
- **Agente/SesiÃ³n:** [nombre del agente o "manual"]
- **Causa raÃ­z:** [quÃ© pasÃ³ y por quÃ©]
- **SÃ­ntoma:** [quÃ© se observÃ³ â€” pantalla colgada, timeout, error especÃ­fico]
- **SoluciÃ³n:** [quÃ© se hizo para resolverlo]
- **PrevenciÃ³n:** [quÃ© cambio de configuraciÃ³n, proceso o documento evita que esto vuelva a pasar]
- **Tags:** #tag1 #tag2
```

---

## Errores activos

### E-001: Agente bloqueado al ejecutar php artisan serve

- **Fecha:** 2026-07-05
- **Agente/SesiÃ³n:** urbania (general)
- **Causa raÃ­z:** `Start-Process -NoNewWindow php artisan serve` lanza un proceso servidor HTTP que
  nunca termina (loop infinito esperando requests). La flag `-NoNewWindow` hereda los streams
  stdout/stderr del agente, haciendo que OpenCode espere indefinidamente la salida del proceso hijo.
  Como el servidor nunca emite una seÃ±al de "terminÃ©", la sesiÃ³n queda bloqueada.
- **SÃ­ntoma:** Agente no responde, sesiÃ³n colgada, sin output de error. Es necesario matar el
  proceso manualmente o reiniciar la sesiÃ³n.
- **SoluciÃ³n:**
  1. **No usar `php artisan serve` desde el agente.** Es un proceso de tipo "Servicio" â€” ver
     clasificaciÃ³n en `_system/AGENT_PREAMBLE.md` Â§7.
  2. Para probar requests HTTP sin servidor: usar `php artisan tinker` y ejecutar
     `app()->handle($request)` â€” esto pasa por todo el stack real de Laravel (middleware, validaciÃ³n,
     controlador) sin necesidad de un servidor web.
  3. Si es necesario un servidor persistente: usar `docker compose up -d`.
  4. En Windows, si no hay alternativa: `Start-Process -WindowStyle Hidden php artisan serve`
     (sin `-NoNewWindow`) â€” abre una ventana separada y el agente no espera su salida.
- **PrevenciÃ³n:**
  1. Configurado `bash_default_timeout_ms: 60000` en `opencode.json` â€” cualquier comando que exceda
     60 segundos serÃ¡ matado automÃ¡ticamente.
  2. Agregado protocolo de clasificaciÃ³n de comandos en `_system/AGENT_PREAMBLE.md` Â§7.
  3. Los agentes deben consultar este RUNBOOK antes de ejecutar comandos de infraestructura
     (`_system/AGENT_PREAMBLE.md` Â§6-bis).
- **Tags:** #bloqueo #php-artisan #infraestructura #windows #start-process #timeout

### E-002: Ãndice de codebase-memory desactualizado o sin cÃ³digo fuente

- **Fecha:** 2026-07-07
- **Agente/SesiÃ³n:** urbania (diagnÃ³stico de infraestructura)
- **Causa raÃ­z:** El Ã­ndice de `codebase-memory` para `URBANIA_NEW` se generÃ³ cuando el vault solo contenÃ­a documentaciÃ³n (`.md`), antes de que el cÃ³digo fuente existiera en `code/`. El Ã­ndice solo tiene 534 nodos estructurales (Section, File, Folder) sin edges de CALLS/DATA_FLOWS. No incluye ningÃºn archivo de `code/api/` ni `code/web/`. Adicionalmente, `search_graph` con BM25 no devuelve resultados â€” el Ã­ndice de texto completo no se construyÃ³ (posiblemente modo `fast`).
- **SÃ­ntoma:** `search_graph` devuelve 0 resultados para cualquier query. `trace_path` no encuentra call chains. `get_architecture` solo muestra estructura de carpetas del vault, no del cÃ³digo. Los agentes que intentan usar `codebase-memory` para anÃ¡lisis de cÃ³digo no obtienen datos Ãºtiles.
- **SoluciÃ³n:**
  1. Reindexar el proyecto en modo `full`: `codebase-memory index_repository --repo_path "D:\Programacion\URBANIA_NEW" --mode full`
  2. Verificar que el Ã­ndice ahora incluye archivos bajo `code/api/` y `code/web/`
  3. Confirmar que `search_graph` devuelve resultados para queries como "login controller" o "auth middleware"
- **PrevenciÃ³n:**
  1. El auditor (Check 9) verifica en cada auditorÃ­a que `codebase-memory_index_status` muestra nodos de cÃ³digo y que `search_graph` funciona.
  2. `urbania` ejecuta un health check de `codebase-memory` al inicio de cada sesiÃ³n (ver `06_AGENT_ROLES.md` Â§1 read-set actualizado).
  3. DespuÃ©s de cada feature completado, se reindexa (agregado al gatillo de release-council).
- **Tags:** #codebase-memory #indice #infraestructura #diagnostico #mcp

### E-003: Agente cambia REDIS_CLIENT o dependencias de sistema sin notificar al usuario

- **Fecha:** 2026-07-07
- **Agente/SesiÃ³n:** urbania (diagnÃ³stico de infraestructura)
- **Causa raÃ­z:** El proyecto Urbania configura `REDIS_CLIENT=phpredis` por defecto (`config/database.php` lÃ­nea 67), que requiere la extensiÃ³n PHP `ext-redis` (phpredis) compilada en C vÃ­a PECL. Cuando esta extensiÃ³n no estÃ¡ instalada en el entorno, el agente puede detectar la ausencia y â€” en lugar de notificar al usuario con opciones â€” cambiar silenciosamente `REDIS_CLIENT=predis` en `.env`. Esto es una decisiÃ³n de infraestructura que cambia el comportamiento runtime (cliente Redis en C â†’ PHP puro) sin consentimiento del usuario. El patrÃ³n se extiende a cualquier dependencia de sistema cuya ausencia el agente "resuelva" por su cuenta sin consultar.
- **SÃ­ntoma:** El usuario no sabe que ocurriÃ³ el swap. El proyecto funciona pero con un cliente Redis diferente al configurado por defecto (predis â‰ˆ30% mÃ¡s lento que phpredis). La decisiÃ³n queda invisibilizada â€” el agente no reporta el hallazgo ni pide confirmaciÃ³n.
- **SoluciÃ³n:**
  1. **Nunca cambiar `REDIS_CLIENT` (ni ninguna variable de entorno que afecte el runtime) sin notificar al usuario.** Protocolo ante ausencia de `ext-redis`:
     - Verificar disponibilidad: `php -r "echo extension_loaded('redis') ? 'yes' : 'no';"`
     - Si no estÃ¡ disponible y `REDIS_CLIENT=phpredis`: **notificar al usuario** con las dos opciones:
       - **A)** Instalar `ext-redis` con `pecl install redis` (mÃ¡s rÃ¡pido, requiere acceso al sistema y que el PHP tenga el tooling de PECL)
       - **B)** Cambiar `REDIS_CLIENT=predis` en `.env` (predis ya estÃ¡ en composer.json, no requiere instalaciÃ³n adicional, pero es mÃ¡s lento)
     - **Esperar la decisiÃ³n explÃ­cita del usuario** antes de continuar con cualquier operaciÃ³n que dependa de Redis.
  2. Si el usuario elige la opciÃ³n B, despuÃ©s del cambio ejecutar: `php artisan config:clear`
  3. Este protocolo aplica por extensiÃ³n a cualquier dependencia de sistema ausente: extensiÃ³n PHP faltante, servicio Docker no corriendo, binario del SO no disponible. La regla es: **detectar â†’ notificar con opciones â†’ esperar decisiÃ³n â†’ ejecutar.**
- **PrevenciÃ³n:**
  1. Agregado al RUNBOOK como `E-003`. Los agentes consultan el RUNBOOK antes de ejecutar comandos de infraestructura (`AGENT_PREAMBLE.md` Â§6-bis).
  2. El agente `urbania` debe ejecutar un health check de entorno al inicio de sesiÃ³n que incluya verificaciÃ³n de extensiones PHP requeridas: `ext-redis`, `ext-pdo_mysql`, `ext-bcmath`, `ext-openssl`.
  3. Principio general reforzado en `AGENT_PREAMBLE.md` Â§6: cualquier cambio de configuraciÃ³n de runtime (`.env`, `config/*.php`, `docker-compose.yml`) requiere notificaciÃ³n y confirmaciÃ³n explÃ­cita del usuario â€” nunca es silencioso.
- **Tags:** #ext-redis #redis #phpredis #predis #configuracion #infraestructura #env #notificacion #health-check

### E-004: `verifier` no podÃ­a re-ejecutar `composer ci` / `pnpm ci` â€” permiso incompleto

- **Fecha:** 2026-07-09
- **Agente/SesiÃ³n:** urbania (reportado por OpenCode durante correcciÃ³n de bloques)
- **Causa raÃ­z:** El prompt de `verifier` (`.opencode/agents/verifier.md`) instruye textualmente
  "Re-ejecutas los comandos de CI relevantes tÃº mismo... `composer ci` para API, `pnpm ci` para Web",
  pero su `permission.bash` nunca incluÃ­a esos dos comandos literales â€” solo variantes granulares
  (`composer test*`, `composer stan`, `composer lint`, `pnpm type-check`, `pnpm lint`, `pnpm test*`,
  `pnpm build`). Como ningÃºn patrÃ³n coincidÃ­a con `composer ci` ni `pnpm ci`, la regla de respaldo
  `"*": deny` los bloqueaba. `verifier` corre en `deepseek/deepseek-v4-flash` (modelo dÃ©bil), que al
  chocar con el bloqueo reportÃ³ genÃ©ricamente "no tengo una herramienta de shell" en vez de un error
  de permiso â€” llevando a un diagnÃ³stico incorrecto de que OpenCode carecÃ­a de mecanismo de shell.
- **SÃ­ntoma:** El bloque quedaba trabado en `estado: verifying` sin que `verifier` pudiera completar
  su checklist ("CI re-ejecutado personalmente"). El agente intentÃ³ rutas alternativas (RCE vÃ­a
  Playwright, `COPY TO PROGRAM` en Postgres, etc.) en vez de detectar el permiso faltante.
- **SoluciÃ³n:** Agregado `"composer ci": allow` y `"pnpm ci": allow` al `permission.bash` de
  `verifier.md` â€” ahora coincide exactamente con los comandos que su propio prompt le exige correr.
  Se confirmÃ³ que `api-build` (`"composer *": allow`) y `web-build` (`"pnpm ci": allow` explÃ­cito) ya
  tenÃ­an el permiso correcto para su parte del pipeline; el enrutamiento
  (`api-orchestrator`/`web-orchestrator` â†’ `api-build`/`web-build` â†’ `verifier`) no tenÃ­a ningÃºn
  bug â€” no parte de este pipeline.
- **PrevenciÃ³n:** Al escribir o editar el prompt de un agente, verificar que cada comando exacto que
  el texto le instruye ejecutar tenga un patrÃ³n literal correspondiente en su `permission.bash` â€” un
  wildcard de un comando distinto (`composer test*`) no cubre otro (`composer ci`).
- **Tags:** #verifier #composer-ci #pnpm-ci #permission #bash #deepseek #diagnostico #falso-positivo

### E-005: `@playwright/test@1.61.1` roto en `code/web` â€” `test.describe()` no reconocido en proyecto ESM

- **Fecha:** 2026-07-10
- **Agente/SesiÃ³n:** urbania (cierre de DoD de PROPIEDADES-B06..B09)
- **Causa raÃ­z:** No confirmada con certeza â€” hipÃ³tesis mÃ¡s probable es una doble instanciaciÃ³n del
  registro interno de `@playwright/test` (`_TestTypeImpl`) en un proyecto con `"type": "module"`
  (`code/web/package.json`): el runner de Playwright parece cargar `playwright.config.ts` por una
  ruta de resoluciÃ³n de mÃ³dulos distinta a la que usa para cargar los archivos `*.spec.ts`, dejando
  el objeto `test` importado en el spec desconectado del contexto de ejecuciÃ³n activo del runner.
  Descartado: no es un problema de versiÃ³n de Node â€” se reprodujo idÃ©ntico en Node v25.9.0 y en
  Node v22.23.1 (instalado vÃ­a `nvm install 22` especÃ­ficamente para probar esta hipÃ³tesis).
  Descartado: no hay dependencias duplicadas de `@playwright/test` ni de `playwright` en
  `node_modules` (`pnpm list playwright @playwright/test -r` muestra una sola instancia).
  No es especÃ­fico de los specs nuevos de esta sesiÃ³n â€” el spec preexistente
  `e2e/auth/login.spec.ts` (de `AUTH-B06`) falla exactamente igual.
- **SÃ­ntoma:** Cualquier invocaciÃ³n de `npx playwright test` (incluyendo `--list`, que ni siquiera
  lanza un navegador) falla en el primer `test.describe()` de cualquier archivo con:
  `Error: Playwright Test did not expect test.describe() to be called here.` seguido de
  `Error: No tests found.` â€” 0 tests recolectados, 0 archivos.
- **SoluciÃ³n:** Sin resolver. Probado sin Ã©xito, exhaustivamente:
  1. Cambiar la versiÃ³n de Node activa (`nvm use 22.23.1` en vez de `v25.9.0`) â€” mismo error.
  2. Downgrade de `@playwright/test` a `1.60.0` â€” mismo error, incluso con un spec trivial de una
     sola lÃ­nea (`test("x", () => expect(1+1).toBe(2))`) sin ningÃºn import del proyecto, aliases de
     `@/` ni helpers â€” descarta que la causa estÃ© en el cÃ³digo de los specs.
  3. ReinstalaciÃ³n forzada (`pnpm install --force`) tras borrar las carpetas de playwright en el
     store de pnpm â€” mismo error.
  4. Downgrade a la versiÃ³n **exacta que especifica el `package.json` commiteado**,
     `@playwright/test@1.49.0` (el rango `^1.49.0` del lockfile habÃ­a resuelto silenciosamente a
     `1.61.1` porque esa era la Ãºltima 1.x disponible cuando se generÃ³ el lockfile â€” no hay drift
     entre lo commiteado y lo instalado, es una resoluciÃ³n de rango semver legÃ­tima pero
     inesperadamente lejana). Con `1.49.0` exacto y su Chromium correspondiente
     (`npx playwright install chromium`), **mismo error idÃ©ntico**.
  Con 4 versiones distintas de Playwright (1.49.0, 1.60.0, 1.61.1, y descartada la exploraciÃ³n de
  `1.62.0-alpha-*` por no ser estable) y 2 versiones de Node fallando de forma idÃ©ntica en un spec
  mÃ­nimo sin ninguna dependencia del proyecto, se descarta con alta confianza que sea una regresiÃ³n
  de Playwright. La causa mÃ¡s probable pasa a ser algo especÃ­fico de este entorno/proyecto:
  `"type": "module"` en `code/web/package.json` combinado con Windows + pnpm (symlinks) rompiendo el
  mecanismo interno de Playwright para registrar el runner activo (`TestTypeImpl._currentSuite`)
  antes de que el archivo de test se evalÃºe. No investigado por quedar fuera de un tiempo
  razonable de sesiÃ³n: forzar `"type": "commonjs"` temporalmente para playwright.config, o abrir un
  issue upstream con un repro mÃ­nimo.
- **Impacto:** Bloquea la verificaciÃ³n visual automatizada (Playwright) exigida por el DoD de
  cualquier bloque `web` â€” no es especÃ­fico de `PROPIEDADES`. `PROPIEDADES-B06/B07/B08/B09` se
  cerraron a `verifying` sin este paso, dejÃ¡ndolo documentado como pendiente explÃ­cito en cada card
  (ver `_state/BOARD.md`). El spec ya escrito para estos 4 bloques
  (`code/web/e2e/propiedades/propiedades.spec.ts`, hace login real contra el backend en Docker con
  `admin@urbania.test` / `Admin123!` y recorre los criterios de aceptaciÃ³n de las 4 pantallas) queda
  listo para correr en cuanto se resuelva este bloqueo â€” no requiere cambios de cÃ³digo, solo
  `npx playwright test e2e/propiedades`.
- **PrevenciÃ³n:** Antes de asumir que un fallo de Playwright es del cÃ³digo bajo prueba, correr un
  spec preexistente conocido-bueno (`e2e/auth/login.spec.ts`) para confirmar si el fallo es del
  entorno o del cÃ³digo nuevo â€” asÃ­ se descartÃ³ rÃ¡pidamente que fuera un problema de los specs de
  `PROPIEDADES` en este caso.
- **Tags:** #playwright #e2e #esm #test-describe #node-version #propiedades #dod #bloqueo

### E-006: `$wrap` de JsonResource rompÃ­a el envelope de POST/PATCH en property-types y property-statuses

- **Fecha:** 2026-07-10
- **Agente/SesiÃ³n:** urbania (verificaciÃ³n de contrato alternativa a Playwright, ver `E-005`)
- **Causa raÃ­z:** `PropertyTypeResource` y `PropertyStatusResource`
  (`code/api/src/Properties/Infrastructure/Http/Resources/`) declaraban
  `public static $wrap = 'property_type';` / `'property_status';`. Laravel solo aplica `$wrap`
  cuando el Resource se serializa vÃ­a `->response()`/`->toResponse()` (usado en
  `PropertyTypeController::store()` y `::update()`), no cuando el controlador envuelve
  manualmente con `response()->json(['data' => ...])` (usado en `index()` y `show()`). Resultado:
  `GET` devolvÃ­a `{data: {...}}` (correcto) pero `POST`/`PATCH` devolvÃ­an
  `{property_type: {...}}` / `{property_status: {...}}` â€” violando el contrato congelado
  `LOCK-PROPIEDADES-01`, que documenta `{data: {...}}` para los tres verbos
  (`api/endpoints/PROPIEDADES.md` lÃ­neas 78, 113, 153). Los tests de PHP
  (`PropertyTypeTest`/`PropertyStatusTest`) habÃ­an sido escritos contra el bug
  (`$response->json('property_type')`) en vez de contra el contrato documentado, asÃ­ que nunca lo
  detectaron.
- **SÃ­ntoma:** En el frontend real, `useCreatePropertyTypeMutation`/`useUpdatePropertyTypeMutation`
  (y sus equivalentes de status) leen `response.data.nombre` para el toast de Ã©xito. Con
  `response.data === undefined`, eso lanza `TypeError: Cannot read properties of undefined
  (reading 'nombre')` dentro del `onSuccess` de la mutaciÃ³n â€” un error no capturado en cada
  creaciÃ³n/ediciÃ³n exitosa de un tipo o estado de propiedad personalizado. No se detectÃ³ en los
  tests de componente de `PROPIEDADES-B06` porque mockean el hook de API completo (la respuesta
  simulada ya viene con la forma correcta por construcciÃ³n). Se encontrÃ³ al escribir un script de
  verificaciÃ³n de contrato contra el backend real
  (`code/web/scripts/verify-propiedades-contract.mjs`) como sustituto de la verificaciÃ³n visual
  Playwright bloqueada por `E-005`.
- **SoluciÃ³n:**
  1. Cambiar `$wrap` a `'data'` en ambos Resources â€” alinea POST/PATCH con lo que ya hacÃ­an
     GET/show y con el contrato congelado.
  2. Corregir las 3 aserciones de test que esperaban el wrap viejo
     (`tests/Feature/Properties/PropertyTypeTest.php` lÃ­neas 163, 211;
     `PropertyStatusTest.php` lÃ­nea 147) de `$response->json('property_type'/'property_status')`
     a `$response->json('data')`.
  3. Re-verificado: `php artisan test --filter=PropertyType` (12 passed) y `--filter=PropertyStatus`
     (6 passed) dentro del contenedor con `-e DB_HOST=postgres -e DB_PORT=5432` (ver nota abajo
     sobre por quÃ© esas variables son necesarias), y el script de contrato completo
     (51/51 checks, antes 49/51).
- **Nota al margen â€” `php artisan test` dentro del contenedor sin overrides falla:**
  `config/database.php` tiene fallback `env('DB_HOST', 'localhost')` / `env('DB_PORT', '5434')`,
  pensado para correr los tests desde el HOST (donde docker-compose mapea postgres a
  `localhost:5434`). `.env.testing` solo sobreescribe `DB_DATABASE`, asÃ­ que dentro del contenedor
  (`docker exec`) los tests no encuentran esos DB_HOST/DB_PORT y caen al default incorrecto
  (`localhost:5434`, que desde DENTRO del contenedor no es nada). Workaround usado en esta sesiÃ³n:
  `docker exec -e DB_HOST=postgres -e DB_PORT=5432 urbania-php php artisan test ...`. No se
  investigÃ³ si hay una forma de correr los tests desde el host directamente (requerirÃ­a PHP+deps
  instalados fuera de Docker) ni si vale la pena agregar `DB_HOST`/`DB_PORT` a `.env.testing` para
  que `docker exec ... php artisan test` funcione sin overrides â€” queda como mejora futura.
- **Adenda (2026-07-11, verify-council de `DIRECTORIO-B01`):** el mismo problema aplica a Redis -
  `phpunit.xml` fija `REDIS_HOST=127.0.0.1` (pensado tambien para correr desde el host), asi que
  dentro del contenedor cualquier test que toque Redis (ej. `MfaTest`) falla con `Connection refused
  [tcp://127.0.0.1:6379]` a menos que se agregue tambien `-e REDIS_HOST=redis` al `docker exec`.
  Comando completo verificado: `docker exec -e DB_HOST=postgres -e DB_PORT=5432 -e REDIS_HOST=redis
  urbania-php php artisan test --parallel` (232/232 passed). Sin esto, hasta 40 tests ajenos al
  bloque que se este verificando pueden aparecer como fallidos y llevar a una falsa alarma de
  regresion.
- **PrevenciÃ³n:** Cuando se agregue una verificaciÃ³n de contrato como la de `E-005`/este bug
  (`code/web/scripts/verify-propiedades-contract.mjs`), correrla tras cualquier cambio en
  Resources/Controllers de `code/api` antes de dar por buena la integraciÃ³n â€” los tests de
  componente del frontend (con API mockeada) y los tests de PHP (si estÃ¡n mal escritos, como aquÃ­)
  no son suficientes por sÃ­ solos para detectar un envelope de respuesta incorrecto.
- **Tags:** #propiedades #json-resource #wrap #contrato #property-types #property-statuses #bug-real #dod

---

## Template para nuevas entradas (copiar y pegar)

```markdown
### E-NNN: [TÃ­tulo descriptivo]
- **Fecha:** YYYY-MM-DD
- **Agente/SesiÃ³n:** [nombre]
- **Causa raÃ­z:** [quÃ© pasÃ³]
- **SÃ­ntoma:** [quÃ© se observÃ³]
- **SoluciÃ³n:** [quÃ© se hizo]
- **PrevenciÃ³n:** [cÃ³mo evitar que vuelva a pasar]
- **Tags:** #tag1 #tag2
```


### E-007: Login vía formulario React lento/intermitente en tests headless de Playwright

- **Fecha:** 2026-07-11
- **Agente/Sesión:** urbania (verificación visual de PROPIEDADES-B06..B09)
- **Causa raíz:** No confirmada. Hipótesis: rate limiting del backend ante múltiples logins consecutivos desde tests headless (el POST /api/v1/auth/login pasa de ~200ms a 10-30s en ciertas ejecuciones), o race condition en RequireAuth que intenta POST /auth/refresh concurrentemente con el login. El backend responde normalmente a requests individuales (docker logs urbania-php muestra respuestas 200 en ~200ms), pero bajo carga de tests falla intermitentemente.
- **Síntoma:** tests de Playwright que usan page.fill() + page.click(''Iniciar sesión'') en el LoginPage (glass-morphism, React) se quedan con el botón en estado "Iniciando sesión..." y el waitForURL("**/dashboard") hace timeout a los 15-30s. El waitForResponse para /api/v1/auth/login también tiene timeout. No todos los tests fallan — es intermitente (~2/7 pasan, ~5/7 fallan).
- **Solución:** Workaround: verificación visual manual con Playwright MCP browser tool (conexión persistente, sin rate limiting) + screenshots como evidencia. Alternativa futura: login vía API (
equest.post + sessionStorage.setItem para evitar el formulario React por completo) o investigar el rate limiting del backend (config/auth.php, RateLimiter de Laravel).
- **Prevención:** Al escribir specs de Playwright que requieran login, considerar usar eforeAll con login vía API en vez de eforeEach con formulario React — un solo login por suite en vez de uno por test.
- **Tags:** #playwright #login #rate-limiting #headless #flaky #propiedades

### E-008: curl sin Accept-application-json contra endpoint protegido cuelga nginx-php-fpm

- **Fecha:** 2026-07-11
- **Agente/Sesion:** urbania (verificacion funcional real de DIRECTORIO-B02)
- **Causa raiz:** Con APP_DEBUG=true, cuando una request a un endpoint auth:api sin token no envia el header Accept: application/json, Laravel intenta renderizar la pagina HTML de error de debug (incluye un editor de codigo con sintaxis resaltada embebido) en vez de devolver el 401 JSON directo del middleware Authenticate. Ese render HTML es patologicamente lento en este entorno (Docker Desktop/Windows) y a veces excede el limite de 30s de max_execution_time, dejando el proceso PHP colgado y agotando el pool de php-fpm/nginx hasta que ambos quedan sin responder (nginx pasa a unhealthy en docker ps).
- **Sintoma:** curl sin auth y sin headers contra un endpoint protegido responde 500 con una pagina HTML enorme (o 504/timeout si el pool ya esta agotado por intentos anteriores). Afecta por igual a endpoints ya done (property-types, auth/me) - no es un bug de un bloque nuevo, es un comportamiento del entorno de debug.
- **Solucion:** Agregar siempre el header Accept: application/json a cualquier curl de verificacion manual contra un endpoint protegido - con ese header, el 401 responde en milisegundos como JSON limpio, igual que ya hacen los tests de Pest (getJson() lo setea automaticamente, por eso los tests nunca mostraron el problema). Si el entorno ya quedo colgado: reiniciar los contenedores urbania-php y urbania-nginx lo recupera en unos segundos.
- **Prevencion:** Toda verificacion funcional real con curl en este proyecto debe incluir el header Accept: application/json en cada request, incluidos los casos negativos de sin auth - no solo por prolijidad de la respuesta, sino porque omitirlo puede tumbar el entorno de desarrollo completo para el resto de la sesion.
- **Tags:** #curl #laravel #debug-mode #auth #timeout #nginx #php-fpm #verificacion-funcional
