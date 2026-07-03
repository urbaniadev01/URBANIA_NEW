---
tipo: referencia
proyecto: api
actualizado: 2026-07-03
---

# API_TESTING — Estrategia de pruebas

## 1. Capas y qué cubre cada una

| Capa | Qué prueba | Comando |
|---|---|---|
| Unit | Domain puro (entidades, value objects, reglas de negocio) sin framework ni BD | `composer test:unit` |
| Feature | Endpoints HTTP completos, contra PostgreSQL real (nunca SQLite in-memory) | `composer test:feature` |
| Integration | Interacción entre bounded contexts vía eventos/Shared | `composer test:integration` |
| Security | Casos de autorización/autenticación negativos — el lugar donde viven los tests que un gap tipo CAMBIO-011 habría atrapado | `composer test:security` |

## 2. Regla dura sobre casos negativos

Todo bloque que introduce una acción de escritura o un gate de autorización debe tener, como mínimo,
un test que ejercite **cada caso negativo de su tabla de criterios de aceptación** — no basta con
cubrir el camino feliz. Esto es lo que hace el DoD verificable en la práctica (ver
[[../_system/05_DEFINITION_OF_DONE]] §2): sin estos tests, la salida de `composer test` no demuestra
que el criterio de aceptación negativo se cumple.

Ejemplo concreto que este vault existe para prevenir: un test de `POST /auth/register` que **solo**
verifica "con token válido, se crea el usuario" es insuficiente — debe existir también "sin token",
"con token expirado" y "con token ya consumido", cada uno esperando `403`/`INVITATION_TOKEN_INVALID`.

## 3. Cobertura

`composer test:coverage` — mínimo 80% como piso, no como objetivo. Un bloque no llega a `verifying`
con cobertura por debajo del piso vigente en el momento.

## 4. Datos de prueba

Sin seeders de demostración todavía — se introducen cuando el primer feature con datos de ejemplo lo
requiera, documentados en su propia tarjeta de bloque (no existe un `DemoDataSeeder` global
pre-poblado como en el vault anterior; nace cuando un bloque real lo necesita).
