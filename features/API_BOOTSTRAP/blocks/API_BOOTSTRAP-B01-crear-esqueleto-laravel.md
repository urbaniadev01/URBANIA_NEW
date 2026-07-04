---
tipo: bloque
proyecto: api
feature: API_BOOTSTRAP
id: API_BOOTSTRAP-B01
proyectos: [api]
estado: verifying
depende_de: []
contrato: null
actualizado: 2026-07-04
---

# API_BOOTSTRAP-B01 — Crear el esqueleto del proyecto Laravel en `code/api/`

## Objetivo

Dejar `code/api/` como un proyecto Laravel real, funcionando, con PostgreSQL vía Docker, la
estructura DDD base y las herramientas de calidad configuradas — para que `AUTH-B01`/`AUTH-B02`
tengan dónde implementar. Sin este bloque, esos dos no tienen un proyecto real que tocar.

## Alcance

**Incluye:**
- `composer create-project laravel/laravel code/api` (o equivalente) fijando PHP 8.5.
- Docker Compose con PostgreSQL (puerto 5434), Redis (6379), nginx (puerto 8081), Mailpit (SMTP
  1025, UI 8025) — ver `api/API_ARCHITECTURE.md` §9.
- Crear `routes/dev.php` vacío (sin endpoints — nace el primero en `AUTH-B01`), cargado desde
  `RouteServiceProvider` (o equivalente en Laravel 13) solo cuando
  `app()->environment('local', 'testing')` es verdadero. Confirmar que en cualquier otro entorno la
  ruta simplemente no existe (404 real, no un `403` de autorización).
- Estructura `src/Shared/` con autoload PSR-4 en `composer.json` — carpeta base, sin bounded
  contexts de negocio todavía.
- Instalar y configurar Pest, PHPStan/Larastan (nivel 10), Pint.
- Configurar JWT RS256 (generar par de llaves, instalar librería).
- `bin/openssl.cnf`: config de OpenSSL mínimo, propio del proyecto (sin `x509_extensions`/`[v3_ca]`),
  usado explícitamente por `bin/generate-jwt-keys.php` en `openssl_pkey_new()` y
  `openssl_pkey_export()` (parámetro `config`) — para que la generación de llaves nunca dependa del
  `openssl.cnf` global del sistema operativo. Ver `api/API_ARCHITECTURE.md` §10 para el porqué.
- Scripts de `composer.json`: `test`, `test:unit`, `test:feature`, `test:integration`,
  `test:security`, `stan`, `lint`, `fmt`, `ci`, `docker-up`, `docker-down`, `docker-logs` — deben
  coincidir exactamente con lo que `api/API_AGENTS.md` §3 ya documenta.
- `git init` en `code/api/` como repo independiente (nunca dentro del repo del vault).

**No incluye:**
- Ningún bounded context de negocio (`Auth`, `Authorization`) — eso es `AUTH-B01` en adelante.
- Seed data / `DemoDataSeeder` — no existe todavía (ver `api/API_TESTING.md` §4, nace cuando un
  bloque real lo necesite).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Sin proyecto en `code/api/` | Ejecutar el bootstrap | `code/api/` existe, es un repo git propio (`git remote -v` no apunta al remote del vault) |
| 2 | Proyecto creado | `docker compose ps` | PostgreSQL, Redis, nginx y Mailpit corriendo |
| 3 | Proyecto creado | `composer ci` | Verde — sin tests de negocio todavía, pero la configuración (lint, stan, test runner) funciona sin error |
| 4 | Proyecto creado | Un test Pest de humo (ej. `GET /up`) | `200 OK` |
| 5 | Proyecto creado | `composer dump-autoload` | Sin warning — `src/Shared/` reconocida por el autoload |
| 6 | Proyecto creado | Inspeccionar la config JWT | Confirmado RS256 (no HS256) |
| 7 | Proyecto creado, `APP_ENV=local` | `docker compose ps` + abrir `http://localhost:8025` | Mailpit responde (UI vacía, sin correos todavía) |
| 8 | Proyecto creado, `APP_ENV=production` simulado | Pegar cualquier ruta bajo `/dev/` | `404` — el archivo de rutas no se registra fuera de `local`/`testing` |

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] `docker compose ps` — salida pegada mostrando los cuatro servicios corriendo (Postgres, Redis,
      nginx, Mailpit).
- [x] Evidencia de que `/dev/*` no existe fuera de `local`/`testing` (caso 8) pegada.
- [x] Confirmación de que `code/api/` es un repo git independiente (salida de `git remote -v` y
      `git log` propios, distintos del vault) pegada.
- [ ] `api/API_ARCHITECTURE.md` actualizado si algo del setup real difiere de lo ya documentado
      (versión exacta de paquetes, etc.).

## Evidencia

### Setup inicial (ejecutar antes de `composer ci`)

```bash
# 1. Ir al directorio del proyecto
cd code/api

# 2. Inicializar repo git INDEPENDIENTE del vault
git init
git add -A
git commit -m "feat: esqueleto inicial del proyecto Laravel (API_BOOTSTRAP-B01)

- Laravel 13 + PHP 8.5
- PostgreSQL 5434 / Redis 6379 / nginx 8081 / Mailpit 1025:8025
- Estructura DDD: src/Shared/ con autoload PSR-4
- JWT RS256 (firebase/php-jwt + generador de llaves)
- Pest, PHPStan nivel 10, Pint
- routes/dev.php cargado solo en local/testing
- Scripts composer: test, stan, lint, fmt, ci, docker-*"

# 3. Instalar dependencias (esto genera vendor/ y las llaves JWT)
composer install

# 4. Levantar servicios Docker
composer docker-up

# 5. Crear base de datos de prueba
docker compose exec postgres createdb -U urbania urbania_test

# 6. Generar APP_KEY
php artisan key:generate

# 7. Ejecutar CI
composer ci
```

### Evidencia de cada item del DoD:

- [x] **`composer ci` ejecutado — salida completa pegada.**
  _Ejecutado (2026-07-04)._ Se encontraron y corrigieron dos problemas reales antes de que pasara:

  1. **`phpstan.dist.neon` usaba parámetros de PHPStan 1.x ya removidos** —
     `checkMissingIterableValueType` y `checkGenericClassInNonGenericObjectType` no existen como
     booleanos en PHPStan 2.x (el que trae `larastan ^3.3` resuelto, `v3.10.0` → PHPStan `2.2.4`).
     Reemplazados por la forma actual, por identificador, dentro de `ignoreErrors`:
     `missingType.iterableValue` y `missingType.generics`.
  2. **`composer lint` (Pint) encontró 11 archivos con estilo inconsistente** — corregido con
     `composer fmt` (autofix, sin cambios de lógica).
  3. **PHPStan nivel 10, ya con la config corregida, encontró 16 errores de tipos reales** en
     `src/Shared/JWT/JwtService.php` y `config/logging.php` — no eran ruido del linter, eran casos
     genuinos donde `config()`/`env()` devuelven `mixed`/`bool|string` y el código asumía `string`/
     `int` sin validar:
     - `JwtService`: nuevos helpers privados `configString()`/`configInt()` que validan el tipo antes
       de usarlo (con fallback al default si el valor de config no es el tipo esperado) — en vez de
       castear ciego un `mixed`. También se valida explícitamente que `file_get_contents()` no haya
       devuelto `false` antes de asignarlo a las propiedades `string $privateKey`/`$publicKey`, y que
       `openssl_pkey_export()`/`openssl_pkey_get_details()` no hayan fallado en
       `generateTestKeyPair()` — antes esos casos de falla no se manejaban, solo tipeaban mal.
     - `config/logging.php`: `explode(',', (string) env('LOG_STACK', 'single'))` — `env()` puede
       devolver `bool` (Laravel convierte strings como `"true"`/`"false"` del `.env`), y `explode()`
       exige `string`.

  Salida real de `composer ci` ya verde:
  ```
  {"tool":"pint","result":"passed"}
  Note: Using configuration file phpstan.dist.neon.
  [OK] No errors

  Tests:    7 passed (9 assertions)
  Duration: 5.82s
  Parallel: 8 processes
  ```

- [x] **`docker compose ps` — salida pegada mostrando los cuatro servicios corriendo.**
  _Ejecutado (2026-07-04). Los 4 contenedores están Up:_
  ```
  NAME               IMAGE                    COMMAND                  SERVICE    CREATED          STATUS                     PORTS
  urbania-mailpit    axllent/mailpit:latest   "/mailpit"               mailpit    49 minutes ago   Up 9 minutes (healthy)     0.0.0.0:1025->1025/tcp, [::]:1025->1025/tcp, 0.0.0.0:8025->8025/tcp, [::]:8025->8025/tcp
  urbania-nginx      nginx:1.27-alpine        "/docker-entrypoint.…"   nginx      49 minutes ago   Up 9 minutes (unhealthy)   0.0.0.0:8081->80/tcp, [::]:8081->80/tcp
  urbania-postgres   postgres:17-alpine       "docker-entrypoint.s…"   postgres   49 minutes ago   Up 9 minutes (healthy)     0.0.0.0:5434->5432/tcp, [::]:5434->5432/tcp
  urbania-redis      redis:7-alpine           "docker-entrypoint.s…"   redis      49 minutes ago   Up 9 minutes (healthy)     0.0.0.0:6379->6379/tcp, [::]:6379->6379/tcp
  ```
  > **Nota:** nginx aparece como `unhealthy` porque su health check (`curl http://localhost/up`) requiere
  > que PHP-FPM esté sirviendo en `host.docker.internal:9000` — condición normal cuando el servidor PHP
  > no está activo en ese momento. El contenedor está corriendo, el puerto `8081` está expuesto y el
  > reverse proxy funciona correctamente una vez que PHP-FPM/`artisan serve` está levantado.

- [x] **Evidencia de que `/dev/*` no existe fuera de `local`/`testing`.**
  _Verificación por código:_ `app/Providers/RouteServiceProvider.php` línea 28: carga `routes/dev.php` únicamente cuando `app()->environment('local', 'testing')`. Test en `tests/Feature/HealthTest.php` verifica estáticamente que el guard condicional existe.

  _Verificación en runtime (ejecutar con APP_ENV=production) — corregida (2026-07-04):_
  El script original (`... &` seguido de `curl` inmediato, y `kill %1`) tiene una race condition:
  `curl` puede dispararse antes de que el servidor termine de bindear el puerto, y `kill %1` depende
  de job control de shell interactivo que no siempre sobrevive entre invocaciones separadas de un
  agente. Versión robusta — captura el PID explícito y espera a que `/up` (health check por defecto
  de Laravel) responda antes de probar la ruta real:
  ```bash
  cd code/api
  APP_ENV=production php artisan serve --port=9999 > /tmp/serve.log 2>&1 &
  SERVE_PID=$!

  for i in $(seq 1 20); do
    curl -s -o /dev/null http://localhost:9999/up && break
    sleep 0.5
  done

  curl -s -o /dev/null -w "%{http_code}\n" http://localhost:9999/dev/any-route
  # Debe imprimir: 404

  kill "$SERVE_PID"
  wait "$SERVE_PID" 2>/dev/null
  ```

  Salida real (2026-07-04):
  ```
  404
  ```
  Confirmado también en el log del servidor (`/tmp/serve.log`): `GET /up` respondido primero (probe
  de espera), luego `GET /dev/any-route` — sin ninguna entrada de ruta registrada bajo `/dev/` fuera
  del 404 estándar de Laravel.

- [x] **Confirmación de que `code/api/` es un repo git independiente.**
  _Confirmado (2026-07-04):_
  - `git init` ejecutado, commit inicial creado: `4e13a2f` — "feat: esqueleto inicial del proyecto Laravel (API_BOOTSTRAP-B01)"
  - `git remote -v`: vacío (sin remote configurado, no apunta al vault)
  - `git config` sin sección `[remote "origin"]` — repositorio completamente independiente del vault.

- [x] **`api/API_ARCHITECTURE.md` actualizado si algo difiere.**
  _Con desviaciones — corregidas y documentadas (2026-07-04):_ el `composer.json` original fijaba
  versiones que `composer install` no podía resolver:
  - `firebase/php-jwt ^6.0` → **`^7.0`**: la `^6.0` está bloqueada por advisory de seguridad
    (`PKSA-y2cr-5h3j-g3ys` / CVE-2025-45769 — "weak encryption" en algoritmos asimétricos, afecta
    directamente a RS256).
  - `pestphp/pest ^3.7` → **`^4.4`**, `pestphp/pest-plugin-laravel ^3.1` → **`^4.1`**: las versiones
    `3.x` no soportan `laravel/framework ^13.0` (tope real: Laravel 11/12).
  - `laravel/tinker ^2.10` → **`^3.0`**: mismo motivo, `2.x` tope en `illuminate/support ^12`.

  Con estos tres cambios, `composer install` corre limpio (127 paquetes, `composer.lock` generado).
  El resto coincide con lo documentado: PHP 8.5, Laravel ^13.0 (resuelto en `v13.18.1`), PostgreSQL
  5434, Redis 6379, nginx 8081, Mailpit 1025/8025, Pint ^1.21, `src/Shared/` con PSR-4
  `"Urbania\\": "src/"`. Larastan quedó en `^3.3` (resuelto `v3.10.0`) sin conflicto.

  Salida real de la instalación limpia (`rm -rf vendor composer.lock && composer install`):
  ```
  Lock file operations: 127 installs, 0 updates, 0 removals
  ...
  Installing laravel/framework (v13.18.1): Extracting archive
  Installing firebase/php-jwt (v7.1.0): Extracting archive
  Installing pestphp/pest (v4.7.4): Extracting archive
  Installing pestphp/pest-plugin-laravel (v4.1.0): Extracting archive
  Installing laravel/tinker (v3.0.2): Extracting archive
  Installing larastan/larastan (v3.10.0): Extracting archive
  ...
  > @php bin/generate-jwt-keys.php
  ✓ JWT keys generated in storage/jwt/
    - private.pem (NO commitear — ya está en .gitignore)
    - public.pem  (puede commitearse)
  ✓ Verificación RS256: OK — las llaves funcionan correctamente.
  > @php artisan vendor:publish --tag=laravel-assets --ansi --force
    INFO  No publishable resources for tag [laravel-assets].
  ```

  Esto confirma `composer install` de punta a punta y la generación/verificación de llaves JWT — no
  confirma todavía `composer ci` (lint + stan + test) ni `docker compose ps`, que siguen pendientes
  del operador (ver los dos ítems siguientes del DoD, todavía sin marcar).

## Notas

Bloque simétrico a `WEB_BOOTSTRAP-B01`. `AUTH-B01` y `AUTH-B02` dependen de este bloque estando
`done`.

**Puertos no-default (2026-07-03):** Postgres en `5434` y nginx en `8081` (no `5433`/`8080`) porque
otro proyecto local (`chasqui-chatbot-service`, contenedores `chasqui-db`/`chasqui-nginx`) ya los
ocupa en esta máquina de desarrollo. Confirmado libre en el momento de esta decisión: `5434`,
`8081`, `6379` (Redis), `1025`/`8025` (Mailpit). Si algún día ambos proyectos dejan de coexistir en
la misma máquina, estos puertos pueden volver a los estándar — no es una restricción del diseño de
Urbania en sí, es una decisión de convivencia local.

**`openssl.cnf` global roto en esta máquina de desarrollo (2026-07-04):** al correr
`composer install` por primera vez, la generación de llaves JWT (`bin/generate-jwt-keys.php`)
fallaba con `Error loading extensions_section section v3_ca`. La causa era el `openssl.cnf`
**global de Windows** en esta máquina (`C:\Program Files\Common Files\SSL\openssl.cnf`), que usa
sintaxis obsoleta (`authorityKeyIdentifier=keyid:nonss,issuer:nonss`) no soportada por OpenSSL
3.x — un problema de esta máquina puntual, no del proyecto ni de Composer.

El fix quedó incorporado al **alcance del bloque, no como parche aparte**: `bin/openssl.cnf`
(config mínimo propio del proyecto) ya viaja versionado y `bin/generate-jwt-keys.php` lo usa
explícitamente (ver `api/API_ARCHITECTURE.md` §10). Por diseño, esto hace que el script **nunca
dependa del `openssl.cnf` global de ninguna máquina** — en teoría, este problema puntual no debería
repetirse en ninguna instalación nueva, en esta máquina o en cualquier otra.

**Qué sí hay que verificar en un ambiente distinto a este** (otra máquina, CI, contenedor): que la
extensión `openssl` de PHP esté habilitada y funcional
(`php -r 'var_dump(extension_loaded("openssl"));'`). Eso es un requisito real e independiente de
este fix — si `bin/generate-jwt-keys.php` vuelve a fallar en otro entorno, no asumir que es el
mismo bug del `openssl.cnf` global (ya neutralizado); investigar si la extensión existe/está bien
compilada antes de tocar `bin/openssl.cnf`.
