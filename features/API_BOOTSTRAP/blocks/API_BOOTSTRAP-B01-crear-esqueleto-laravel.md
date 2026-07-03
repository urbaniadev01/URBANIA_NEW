---
tipo: bloque
proyecto: api
feature: API_BOOTSTRAP
id: API_BOOTSTRAP-B01
proyectos: [api]
estado: ready
depende_de: []
contrato: null
actualizado: 2026-07-03
---

# API_BOOTSTRAP-B01 — Crear el esqueleto del proyecto Laravel en `code/api/`

## Objetivo

Dejar `code/api/` como un proyecto Laravel real, funcionando, con PostgreSQL vía Docker, la
estructura DDD base y las herramientas de calidad configuradas — para que `AUTH-B01`/`AUTH-B02`
tengan dónde implementar. Sin este bloque, esos dos no tienen un proyecto real que tocar.

## Alcance

**Incluye:**
- `composer create-project laravel/laravel code/api` (o equivalente) fijando PHP 8.5.
- Docker Compose con PostgreSQL (puerto 5433), Redis, nginx (puerto 8080).
- Estructura `src/Shared/` con autoload PSR-4 en `composer.json` — carpeta base, sin bounded
  contexts de negocio todavía.
- Instalar y configurar Pest, PHPStan/Larastan (nivel 10), Pint.
- Configurar JWT RS256 (generar par de llaves, instalar librería).
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
| 2 | Proyecto creado | `docker compose ps` | PostgreSQL, Redis y nginx corriendo |
| 3 | Proyecto creado | `composer ci` | Verde — sin tests de negocio todavía, pero la configuración (lint, stan, test runner) funciona sin error |
| 4 | Proyecto creado | Un test Pest de humo (ej. `GET /up`) | `200 OK` |
| 5 | Proyecto creado | `composer dump-autoload` | Sin warning — `src/Shared/` reconocida por el autoload |
| 6 | Proyecto creado | Inspeccionar la config JWT | Confirmado RS256 (no HS256) |

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] `docker compose ps` — salida pegada mostrando los tres servicios corriendo.
- [ ] Confirmación de que `code/api/` es un repo git independiente (salida de `git remote -v` y
      `git log` propios, distintos del vault) pegada.
- [ ] `api/API_ARCHITECTURE.md` actualizado si algo del setup real difiere de lo ya documentado
      (versión exacta de paquetes, etc.).

## Evidencia

_Vacío — se completa al ejecutar este bloque._

## Notas

Bloque simétrico a `WEB_BOOTSTRAP-B01`. `AUTH-B01` y `AUTH-B02` dependen de este bloque estando
`done`.
