---
tipo: referencia
proyecto: api
actualizado: 2026-07-03
---

# API_ARCHITECTURE — Stack y estructura (fuente de verdad)

## 1. Stack

- **Laravel 13**, PHP 8.5.
- **PostgreSQL** (Docker Compose, puerto 5433 en desarrollo) — nunca SQLite ni MySQL, ni en tests.
- **Redis** para cache de permisos resueltos (ver [[../shared/adr/ADR-001-actor-party]] §2) y colas.
- **JWT firmado con RS256** para autenticación (par de llaves asimétrico — la API firma con la
  privada, cualquier verificador usa solo la pública).

## 2. Clean Architecture + DDD

Cada carpeta de primer nivel bajo `src/` (excepto `Shared/`) es un **bounded context**:

```
src/<Context>/
├── Domain/          # Entidades, Value Objects, interfaces de Repositorio, Excepciones, Eventos — sin dependencias externas
├── Application/      # UseCases, DTOs, interfaces de Servicio — solo orquestación
├── Infrastructure/    # Repositorios Eloquent, mappers, controllers/requests/resources HTTP, servicios concretos
└── Presentation/       # ServiceProvider + routes.php de este contexto
```

`app/` es scaffolding delgado de Laravel (Console, middleware HTTP, Models, Providers) — la lógica de
negocio vive en `src/`. `routes/api.php` conecta los contextos entre sí.

**Regla dura:** los bounded contexts no se importan entre sí directamente — solo a través de
`Shared/` (utilidades, value objects verdaderamente comunes) o eventos de dominio.

## 3. Naming

| Elemento | Convención | Ejemplo |
|---|---|---|
| Clases | PascalCase | `RegisterUserUseCase` |
| Tablas/columnas de BD | snake_case | `property_occupants`, `invitation_token` |
| Endpoints | kebab-case | `/auth/forgot-password` |
| Service providers | `*ServiceProvider` | `AuthServiceProvider` |
| Mappers Eloquent↔Domain | `*Mapper` | `UserMapper` |
| Casos de uso | `*UseCase` | `LoginUseCase` |
| Repositorios | `*Repository` (interfaz en Domain, implementación en Infrastructure) | `UserRepository` |
| DTOs | `final readonly class` | `final readonly class LoginRequestDto` |

## 4. Manejo de errores de dominio

Toda excepción de dominio extiende una base común y se traduce, en la capa Infrastructure/HTTP, al
formato de error único documentado en [[API_CONTRACT]] §2. El dominio nunca lanza una excepción de
Laravel/Illuminate directamente — lanza su propia excepción tipada y es la capa HTTP la que la
traduce a código + mensaje + `trace_id`.

## 5. Bounded contexts previstos (se registran a medida que se crean, no de antemano)

Este documento no enumera contextos que todavía no existen — un contexto se agrega a esta lista
cuando su primer bloque llega a `done`:

| Contexto | Feature que lo origina | Estado |
|---|---|---|
| `Auth` | [[../features/AUTH/PANORAMA]] | En diseño — bloques `AUTH-B01`/`AUTH-B02` en `ready` |
| `Authorization` | RBAC de [[../shared/adr/ADR-001-actor-party]] | Se crea junto con `AUTH-B05` (middleware RBAC) |

## 6. Seguridad — requisitos que Web debe cumplir del lado cliente

Definidos aquí porque son un requisito de la API hacia cualquier cliente (ver
[[../shared/SYSTEM_CONTRACT]] §1); la implementación concreta del lado Web vive en
`web/WEB_ARCHITECTURE.md`.

- El **access token** es de vida corta y se transmite en el header `Authorization: Bearer`.
- El **refresh token** es de vida más larga, con rotación (ver [[../shared/GLOSSARY]] "Refresh token
  rotation") — la API lo emite pensado para ir en una cookie `httpOnly`, nunca en almacenamiento
  accesible por JavaScript.
- Ningún endpoint de autenticación devuelve el `password_hash` ni ningún secreto en ninguna
  respuesta, ni siquiera en errores de validación.

## 7. Calidad

- **PHPStan (Larastan) nivel 10** — el nivel más estricto, sin excepciones locales sin ADR que lo
  justifique.
- **Pint** para formato — `composer lint` en CI, `composer fmt` para autofix.
- Ver [[API_TESTING]] para la estrategia de pruebas por capa.

## 8. Entorno

```bash
docker compose ps      # PostgreSQL (5433) / Redis / nginx (8080)
composer docker-up
composer docker-down
composer docker-logs
```
