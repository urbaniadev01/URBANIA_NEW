---
tipo: adr
proyecto: shared
actualizado: 2026-07-03
---

# ADR-001: Fundación multi-tenant + RBAC + actor/party canónico

## Estado

Aceptada.

## Contexto

Urbania es un SaaS multi-tenant: administradoras que gestionan múltiples condominios. Esto exige
resolver, desde el primer endpoint, cuatro decisiones de fondo:

1. **Multi-tenancy** — cómo se aísla el dato de cada organización.
2. **RBAC** — cómo se modelan roles y permisos para un dominio con ~14 roles previstos (admin,
   administrador de conjunto, vigilante, residente, propietario, contador, consejo, etc.), en vez de
   un `role` binario.
3. **Actor vs. party** — cómo se distingue "quién ejecuta una acción" (autoría) de "a quién
   pertenece un registro" (residente de una unidad, dueño de una cuenta), dado que no toda persona
   con derechos sobre una unidad tiene necesariamente una cuenta con login (propietario ausente,
   registrado por obligación legal — Ley 675 de 2001).
4. **Scoping de staff** — cómo se representa que un administrador o vigilante opera sobre uno o
   varios condominios específicos, sin duplicar la tabla de asignaciones de rol.

## Decisión

### 1. Multi-tenancy — base compartida + `organization_id` discriminador + RLS

`organizations` (la cuenta) 1—N `condominiums` (el conjunto). Un edificio único es una organización
con un solo condominio, sin caso especial.

- `organization_id` en `users`, `condominiums` y catálogos de nivel organización.
- Lo operativo se scopea por `condominium_id` (que ya implica la organización).
- Aislamiento: **PostgreSQL Row-Level Security** con `current_setting('app.org_id')` fijado por
  request desde el JWT, en middleware compartido. Se arranca con global scopes de Eloquent (bajo
  riesgo, inmediato) y RLS se activa como fast-follow — la columna ya presente lo hace trivial. RLS
  es la garantía a nivel de base de datos de que un `WHERE` olvidado no filtre datos entre tenants.
- Se descarta schema-por-tenant: el perfil es "muchos tenants pequeños"; una base compartida escala
  mejor y abarata migraciones. Aislamiento físico queda reservado para un cliente enterprise que lo
  exija explícitamente.

### 2. RBAC — `roles` / `permissions` / `role_assignments` con scope, resuelto server-side

- Permisos como catálogo fijo `recurso.accion`.
- Roles de sistema + roles personalizados por organización.
- `role_assignments(user_id × role_id × scope_type × scope_id × vigencia)` como tabla central.
- El JWT **no** lleva permisos — solo identidad + `organization_id`. Los permisos efectivos se
  resuelven por request contra las tablas, con cache de invalidación al cambiar un rol.
- Se descarta ABAC: `recurso.accion` + scope cubre el dominio sin la complejidad de atributos
  dinámicos.

### 3. Actor canónico — "party = contact, actor = user"

- **Pertenencia** (dueño de cuenta, residente, radicante de una solicitud) → `contact_id` (+
  `property_id` vía `property_occupants`), porque no toda persona con derechos sobre una unidad
  tiene login.
- **Autoría** (`created_by`, quién ejecutó una acción) → `user_id`, porque la acción la ejecuta
  alguien autenticado.
- **Invariante dura:** todo `user` activo tiene un `contact` asociado (`contacts.user_id` único y
  obligatorio). Un `contact` puede existir sin `user`. `property_occupants` es la única verdad de
  persona↔unidad — no existe una columna de texto libre alternativa.

### 4. Scoping de staff — vía `role_assignments.scope`, sin tabla aparte

No se crea una tabla `user_condominiums`. El alcance vive en
`role_assignments(scope_type ∈ {organization, condominium, tower, unit})`. Para residentes, el
alcance se deriva de `property_occupants` (sus unidades → condominio), no de una asignación de rol
explícita.

## Alternativas consideradas

| Alternativa | Por qué se descartó |
|---|---|
| Schema-por-tenant | No escala para "muchos tenants pequeños"; migraciones costosas |
| Permisos incluidos en el JWT | El token crece y los permisos quedan desactualizados hasta que expire |
| ABAC | Sobredimensionado frente a `recurso.accion` + scope |
| Tabla `user_condominiums` separada | Duplica lo que ya resuelve `role_assignments.scope` |
| `user_id` como único actor en tablas operativas | Excluye propietarios sin login, que la ley obliga a registrar |

## Consecuencias

**Positivas:** RBAC resuelto server-side evita permisos obsoletos en el token; RLS da garantía de
aislamiento a nivel de base de datos; el modelo dual `contact`/`user` cubre el requisito legal de
registrar propietarios sin cuenta.

**Trade-offs:** el RBAC server-side agrega una consulta/cache por request; el orden de
implementación importa (tenant → invariante contact/user → RBAC) y no se puede saltar pasos.

## Alcance de la decisión

Esta decisión es la base de datos y de autorización sobre la que se diseña el feature `AUTH` (ver
[[../../features/AUTH/PANORAMA]]) y cualquier feature futura que toque identidad, pertenencia o
permisos. Afecta directamente:

- [[../SYSTEM_CONTRACT]] §4 (regla de actor y party)
- [[../DATA_MODEL]] (tablas fundacionales: `organizations`, `users`, `contacts`,
  `property_occupants`, `roles`, `permissions`, `role_assignments`)
- `api/API_ARCHITECTURE.md` (módulo `Authorization` separado de `Auth`, middleware de tenant)
