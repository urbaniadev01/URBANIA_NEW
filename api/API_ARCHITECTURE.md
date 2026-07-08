---
tipo: referencia
proyecto: api
actualizado: 2026-07-08
---

# API_ARCHITECTURE — Stack y estructura (fuente de verdad)

## 1. Stack

- **Laravel 13**, PHP 8.5.
- **PostgreSQL** (Docker Compose, puerto 5434 en desarrollo — `5433` colisiona con otro proyecto
  local, ver Notas de `API_BOOTSTRAP-B01`) — nunca SQLite ni MySQL, ni en tests.
- **Redis** para cache de permisos resueltos (ver [[../shared/adr/ADR-001-actor-party]] §2) y colas.
- **JWT firmado con RS256** para autenticación (par de llaves asimétrico — la API firma con la
  privada, cualquier verificador usa solo la pública).
- **OpenSSL** (extensión de PHP, no un paquete de Composer) — requerido para generar el par de
  llaves RS256. Ver §10 para el requisito exacto de entorno y por qué el proyecto no confía en la
  config global de OpenSSL de la máquina.

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
| `Auth` | [[../features/AUTH/PANORAMA]] | `SHIPPED` — AUTH-B01 a AUTH-B09 `done`, Fase 2 Web (B10-B12) `done` |
| `Authorization` | RBAC de [[../shared/adr/ADR-001-actor-party]] | `done` — bloque AUTH-B05 completado (middleware RBAC, tests, documentación) |
| `Mfa` | MFA de [[../features/AUTH/PANORAMA]] | `done` — bloque AUTH-B08 completado (TOTP enrollment, verify, recovery codes, middleware). Pantallas Web en B10-B11. |

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
docker compose ps      # PostgreSQL (5434) / Redis (6379) / nginx (8081) / Mailpit (8025 UI, 1025 SMTP)
composer docker-up
composer docker-down
composer docker-logs
```

## 9. Modo desarrollo / pruebas

Todo flujo de auth que envía un código por fuera (invitación, reset de password, y a futuro MFA)
tiene que poder probarse sin depender de un correo/SMS real:

- **Mailpit** captura todo correo saliente en `local`/`testing` (puerto 1025 SMTP, UI en
  `http://localhost:8025`) — nunca sale de la máquina de desarrollo.
- **`routes/dev.php`** se registra únicamente cuando `app()->environment('local', 'testing')` es
  verdadero — fuera de esos entornos el archivo ni se carga, no es un flag que se pueda dejar
  prendido por error. Vive fuera de `/api/v1/` a propósito: no es parte del contrato del producto
  (ver `API_CONTRACT.md` §1), así que no se congela en `_state/contracts/CONTRACT_LOCKS.md`.
- Cada bloque que introduce un código "enviado por fuera" agrega su propio endpoint bajo esa
  convención como parte de su propio DoD (no se construyen todos por adelantado) — el primero es
  `GET /dev/invitations/last?email=...` en `AUTH-B01`.
- Nunca se expone un secreto de MFA por un endpoint de conveniencia, ni en dev — el patrón para
  cuentas semilla de demo/test vive documentado fuera de este vault (no es contenido de agente).

## 10. Requisitos del entorno de desarrollo

Antes de correr `API_BOOTSTRAP-B01` (o cualquier bloque de API) en una máquina nueva, tiene que
existir:

- **PHP 8.5** con la extensión `openssl` habilitada (`php -m | grep -i openssl`).
- **Composer 2.x**.
- **Docker + Docker Compose** (ver §8).

**OpenSSL — por qué es un requisito explícito y no algo implícito de "tener PHP instalado":**
Generar el par de llaves RS256 (`bin/generate-jwt-keys.php`) llama a `openssl_pkey_new()` /
`openssl_pkey_export()`, que por defecto leen el `openssl.cnf` **global del sistema operativo**. En
máquinas Windows es común que ese archivo global esté desactualizado o no exista donde PHP lo
espera, y el error resultante (`Error loading extensions_section section v3_ca`, típicamente por
sintaxis obsoleta tipo `authorityKeyIdentifier=keyid:nonss,issuer:nonss`, no soportada en OpenSSL
3.x) no tiene nada que ver con el proyecto ni con Composer — es un problema de la config del SO.

Por eso `code/api/bin/openssl.cnf` viaja versionado junto al proyecto desde `API_BOOTSTRAP-B01`: es
un config mínimo (sin `x509_extensions`/`[v3_ca]`, porque aquí solo se genera un par de llaves, no
un certificado) que `bin/generate-jwt-keys.php` pasa explícitamente vía la opción `config` en
**ambas** llamadas (`openssl_pkey_new()` y `openssl_pkey_export()`). Esto es intencional desde el
diseño del bloque, no un parche reactivo — el proyecto nunca depende de que el `openssl.cnf` global
de la máquina sea correcto.

**Consecuencia práctica:** en una instalación nueva, este problema específico no debería reaparecer
— el config global de esa máquina es irrelevante para este script. Si en otra máquina/entorno
(CI, contenedor, otro SO) `bin/generate-jwt-keys.php` vuelve a fallar con un error de OpenSSL, **no
asumir que es el mismo bug** — verificar primero que la extensión `openssl` de PHP esté realmente
habilitada y funcional (`php -r 'var_dump(extension_loaded("openssl"));'`) antes de tocar
`bin/openssl.cnf`, porque en ese caso la causa es otra (extensión faltante, build de PHP sin
OpenSSL, etc.), no la config global rota que este archivo ya neutraliza.
