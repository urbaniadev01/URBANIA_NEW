---
tipo: referencia
proyecto: api
actualizado: 2026-07-03
---

# API_AGENTS — Entrada local del proyecto API

> Punto de entrada cuando ya se sabe que la tarea es 100% local a API (ver
> [[../_system/00_START_HERE]] Paso 1). Este documento no repite la metodología general — solo
> ubica los documentos técnicos de este proyecto y las reglas de oro específicas de Laravel/DDD.

## 0. Dónde vive el código

El proyecto Laravel vive en `code/api/` (repo git independiente de este vault, gitignored — ver
`.gitignore`). Todo comando de esta página se ejecuta desde ahí, no desde la raíz del vault. Si
`code/api/` todavía no existe, el bloque a ejecutar es
`features/API_BOOTSTRAP/blocks/API_BOOTSTRAP-B01-crear-esqueleto-laravel.md` — ningún otro bloque de
API tiene dónde ejecutarse antes de que ese exista.

## 1. Documentos técnicos de este proyecto

| Documento | Cuándo consultarlo |
|---|---|
| [[API_ARCHITECTURE]] | Stack, estructura de carpetas DDD, convenciones de nombres — fuente de verdad de la estructura |
| [[API_CONTRACT]] | Convenciones REST: formato de error, versionado, paginación, rate limiting |
| [[API_DATABASE]] | Esquema real implementado (se llena a medida que los bloques lo crean) |
| [[API_TESTING]] | Qué se prueba y cómo, por capa |
| `endpoints/<FEATURE>.md` | Detalle de request/response de un feature específico |

## 2. Reglas de oro (específicas de API — además de las de `_system/`)

1. **Domain no importa nada de framework.** Ni Eloquent, ni facades de Laravel, ni Illuminate\* en
   `src/<Context>/Domain/`.
2. **Los bounded contexts no se importan entre sí directamente** — solo a través de `Shared/` o
   eventos de dominio.
3. **Autenticación con RS256**, nunca HS256 (evita que el secreto de firma viva en el mismo lugar
   que lo valida).
4. **UUID v7** como clave primaria en toda tabla nueva (ver [[../shared/DATA_MODEL]] §1).
5. **DTOs `final readonly`** — inmutables desde su creación.
6. **Toda migración tiene un `down()` reversible y probado** — no un stub vacío.
7. **Un solo formato de error en todo el API:** `{ "error": { "code", "message", "trace_id" } }` (ver
   [[API_CONTRACT]] §2). Ningún endpoint inventa su propia forma de error.
8. **El gate de autorización real es RBAC** (`role_assignments` + `permissions`, ver
   [[../shared/adr/ADR-001-actor-party]]) — nunca una columna legacy tipo `role` de texto libre como
   único gate de una acción sensible.
9. **El registro de usuario exige invitación válida verificada contra la tabla `invitations`** — no
   basta con que el campo `invitation_token` esté presente y no vacío (ese fue exactamente el hueco
   de seguridad que este vault existe para prevenir; ver criterios de aceptación de `AUTH-B01`).

## 3. Comandos

```bash
composer test              # suite completa (Pest)
composer test:unit
composer test:feature
composer stan               # PHPStan / Larastan, nivel 10
composer lint                # Pint --test
composer fmt                 # Pint --fix
composer ci                  # lint + stan + test — obligatorio antes de marcar un bloque `verifying`
docker compose ps            # PostgreSQL / Redis / nginx vía Docker Compose
php artisan migrate:status
```

Comando de un solo test: `vendor/bin/pest tests/Feature/Auth/LoginTest.php`.
