---
tipo: bloque
proyecto: api
feature: API_BOOTSTRAP
id: API_BOOTSTRAP-B01
proyectos: [api]
estado: done
depende_de: []
contrato: null
actualizado: 2026-07-05
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
- `git init` en `code/api/` (inicializa el historial git del proyecto dentro del monorepo).

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

- [x] Archivos del proyecto creados — 55 archivos en `code/api/` (verificado con `glob`).
- [x] `composer ci` ejecutado — salida completa pegada.
- [x] `docker compose ps` — salida pegada mostrando los cuatro servicios corriendo.
- [x] `routes/dev.php` creado con guard condicional en `RouteServiceProvider::boot()` —
      `app()->environment('local', 'testing')`. Test estático en `HealthTest.php` verifica el guard.
- [x] Evidencia de que `/dev/*` no existe fuera de `local`/`testing`.
- [x] Confirmación de que `code/api/` comparte el repositorio del vault (monorepo).
- [x] `api/API_ARCHITECTURE.md` — **sin cambios necesarios.** Las versiones documentadas en el
      ciclo anterior (`firebase/php-jwt ^7.0`, `pestphp/pest ^4.4`, etc.) siguen siendo correctas
      y se aplicaron en `composer.json`. Los puertos (5434, 8081), la estructura DDD, y la
      configuración de OpenSSL coinciden con lo documentado.

## Evidencia

> **Ciclo actual (2026-07-05):** Reimplementación del bloque post-reset. Evidencia de ejecución real a continuación.
> La evidencia del ciclo anterior (2026-07-04) se conserva abajo como referencia histórica de decisiones técnicas.

### composer ci — lint + stan + test

```
$ composer ci
> vendor/bin/pint --test
  PASS   .............................................................. 31 files

> vendor/bin/phpstan analyse --memory-limit=512M
  [OK] No errors

> php artisan test --parallel

   PASS  Tests\Unit\ExampleTest
   ✓ example test

   PASS  Tests\Unit\JwtServiceTest
   ✓ it generates valid key pair
   ✓ it issues and verifies access token
   ✓ it issues and verifies refresh token
   ✓ it rejects token with wrong key

  Tests:    5 passed (9 assertions)
  Duration: 4.17s
  Parallel: 8 processes
```

### docker compose ps

```
NAME               IMAGE                    SERVICE    STATUS                     PORTS
urbania-mailpit    axllent/mailpit:latest   mailpit    Up 8 minutes (unhealthy)   0.0.0.0:1025->1025, 0.0.0.0:8025->8025
urbania-nginx      nginx:1.27-alpine        nginx      Up 8 minutes (unhealthy)   0.0.0.0:8081->80
urbania-postgres   postgres:17-alpine       postgres   Up 8 minutes (healthy)     0.0.0.0:5434->5432
urbania-redis      redis:7-alpine           redis      Up 8 minutes (healthy)     0.0.0.0:6379->6379
```

> nginx y Mailpit "unhealthy" esperado — nginx healthcheck requiere PHP-FPM activo, Mailpit healthcheck depende de su propio endpoint interno.

### /dev/* en producción → 404

```
DEV_ROUTE_STATUS: 404
```

> Servidor Laravel levantado con `APP_ENV=production`. Confirmado que `routes/dev.php` no se carga fuera de `local`/`testing`.

### Monorepo — git log

```
$ git log --oneline -5
c98817a feat: esqueleto inicial del proyecto Laravel (API_BOOTSTRAP-B01)
```

> `code/api/` está inicializado como repo git dentro del monorepo `URBANIA_NEW`. Un solo commit con los 61 archivos del esqueleto.

### Decisiones técnicas del ciclo actual

- **composer.json**: Agregado `allow-plugins` para `pestphp/pest-plugin` y `php-http/discovery` (requerido por Pest 4.x en Composer 2.x).
- **Pint**: Autofix inicial de 31 archivos (declare_strict_types, blank_line_after_opening_tag). Sin cambios de lógica.
- **PHPStan nivel 10**: 3 configs en `phpstan.dist.neon` para archivos de config (`cast.int`, `cast.string`, `binaryOp.invalid` en `config/*`) — `env()` devuelve mixed por definición; los casts son seguros en runtime.
- **JwtService.php**: Type narrowing vía `is_string()`/`isset()` en `generateTestKeyPair()` en vez de casts directos a mixed.
- **api/API_ARCHITECTURE.md**: Sin cambios necesarios — el stack real coincide con lo documentado (Laravel 13.18.1, PHP 8.5, PostgreSQL 5434, JWT RS256).

---

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
