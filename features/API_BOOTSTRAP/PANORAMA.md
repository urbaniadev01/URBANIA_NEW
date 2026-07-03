---
tipo: feature
proyecto: shared
feature: API_BOOTSTRAP
estado_diseño: approved
actualizado: 2026-07-03
---

# Feature: API_BOOTSTRAP

> **No es una feature de negocio** — es el setup técnico simétrico a
> [[../WEB_BOOTSTRAP/PANORAMA]]: crear el esqueleto real del proyecto Laravel en `code/api/` antes
> de que `AUTH-B01`/`AUTH-B02` puedan implementar nada ahí. Sin este bloque, "ejecutar AUTH-B01" no
> tiene dónde ejecutarse. Aprobación delegada por el usuario al agente para esta sesión puntual.

## 1. Resumen y motivación

El vault referencia `code/api/` como la ubicación del proyecto Laravel (ver
`shared/adr` y `AGENTS.md`/`CLAUDE.md`), pero ese proyecto no existe todavía — es una ruta
documentada, no una carpeta real. Este bloque es el que la crea: Laravel 13 + PHP 8.5 + PostgreSQL
vía Docker Compose, con la estructura DDD base y las herramientas de calidad (Pest, PHPStan nivel
10, Pint) instaladas y funcionando, como su propio repositorio git.

## 2. Capas afectadas

- [x] API
- [ ] Web
- [ ] App

## 3. Relación con otras features

- No depende de ninguna feature.
- Es consumido por: `AUTH-B01`, `AUTH-B02`, y todo bloque de API futuro (dependencia de
  infraestructura, no de negocio).

## 4. Modelo de datos

No aplica.

## 5. Reglas de negocio globales

No aplica.

## 6. Alcance técnico

- Crear el proyecto Laravel 13 (PHP 8.5) en `code/api/`.
- Configurar Docker Compose: PostgreSQL (puerto 5433), Redis, nginx (puerto 8080) — ver
  `api/API_ARCHITECTURE.md` §1 y §8.
- Instalar y configurar Pest, PHPStan/Larastan nivel 10, Pint — ver `api/API_ARCHITECTURE.md` §7.
- Crear la estructura DDD base: carpeta `src/Shared/` con autoload configurado en `composer.json` —
  ver `api/API_ARCHITECTURE.md` §2. Sin bounded contexts de negocio todavía (eso es `AUTH-B01` en
  adelante).
- Configurar JWT RS256 (generación de par de llaves, librería) — ver `api/API_ARCHITECTURE.md` §1.
- Definir en `composer.json` los scripts que `api/API_AGENTS.md` §3 ya documenta como contrato:
  `test`, `test:unit`, `test:feature`, `test:integration`, `test:security`, `stan`, `lint`, `fmt`,
  `ci`, `docker-up`, `docker-down`, `docker-logs`.
- `code/api/` es su propio repositorio git, independiente del vault (ver `.gitignore` — `/code/`).

## 7. Plan de bloques

Un único bloque: ver [[BLOCKS]].

## 8. Checklist de aprobación

- [x] Alcance técnico acotado y verificable (§6)
- [x] No introduce vocabulario de dominio nuevo
- [x] No hay una feature existente que ya cubra esto

> Aprobado — delegación del usuario al agente para esta decisión puntual, registrada aquí.
