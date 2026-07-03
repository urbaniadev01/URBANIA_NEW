---
tipo: feature
proyecto: shared
feature: AUTH
estado_diseño: approved
actualizado: 2026-07-03
---

# Feature: AUTH

> Molde oro del vault. Además de ser la primera feature real, es el ejemplo de referencia de cómo se
> ve un panorama, un plan de bloques y una tarjeta de bloque completos. Su alcance es
> deliberadamente el de la Fase 1 del roadmap: identidad y sesión — no incluye MFA completo,
> recuperación de contraseña, ni vínculo a unidades/propiedades (eso depende de la feature
> Propiedades, todavía no diseñada).

## 1. Resumen y motivación

Todo lo demás en el sistema requiere saber quién está haciendo una petición y si tiene permiso para
hacerla. AUTH es la feature fundacional: registro por invitación, login, renovación de sesión,
cierre de sesión, y el gate de autorización (RBAC) sobre el que se apoyará cualquier feature futura.
Es el primer feature porque nada más puede empezar sin él.

## 2. Capas afectadas

- [x] API (origen del contrato)
- [x] Web
- [ ] App — diferido, ver [[../../app/APP_DEFERRED]]

## 3. Relación con otras features

- No depende de ninguna feature existente (es la primera).
- Es consumido por: toda feature futura que requiera identidad o autorización (todas).
- **Explícitamente fuera de esta feature:** el vínculo de un usuario a una unidad/propiedad
  (`property_occupants`) — eso pertenece a una feature futura (Propiedades / Registro de
  residentes) que todavía no tiene panorama y que dependerá de AUTH una vez exista.

## 4. Modelo de datos

| Entidad | Nueva/Existente | Campo clave | Valor/Referencia | Notas |
|---|---|---|---|---|
| `organizations` | Nueva | — | — | Tenant raíz, ver [[../../shared/adr/ADR-001-actor-party]] |
| `users` | Nueva | `organization_id` | Referencia (`→ organizations.id`) | Identidad de cuenta (actor) |
| `contacts` | Nueva | `user_id` | Referencia, nullable | Todo `user` activo tiene un `contact` (invariante ADR-001) |
| `invitations` | Nueva | `organization_id`, `email` | Referencia + Valor | Token de alta, consumible una sola vez |
| `roles` / `permissions` / `role_assignments` | Nueva | — | Referencia | RBAC — se crean en `AUTH-B05`, no en `AUTH-B01` |

Convenciones de columnas (UUID v7, soft delete, etc.): [[../../shared/DATA_MODEL]] §1.

## 5. Reglas de negocio globales

- Un usuario **solo** se crea mediante invitación válida — no existe registro abierto sin invitación
  (ver criterios de aceptación de `AUTH-B01`; este es el punto exacto que la auditoría del vault
  anterior encontró roto — un token "no vacío" no es lo mismo que un token "válido contra la tabla
  `invitations`").
- El gate de autorización de cualquier acción sensible es RBAC real (`role_assignments` +
  `permissions`), nunca una columna de rol legacy de texto libre (ver `AUTH-B05`).
- El `access_token` nunca se persiste en almacenamiento accesible por JavaScript del lado cliente.

## 6. Mapeo de acciones a endpoints (alto nivel)

| Acción del usuario | Verbo | Endpoint | Bloque |
|---|---|---|---|
| Completar registro con invitación | POST | `/auth/register` | `AUTH-B01` |
| Iniciar sesión | POST | `/auth/login` | `AUTH-B02` |
| Renovar sesión | POST | `/auth/refresh` | `AUTH-B03` |
| Cerrar sesión | POST | `/auth/logout` | `AUTH-B04` |

Detalle completo de request/response en `api/endpoints/AUTH.md` (se llena a medida que cada bloque
llega a `done`).

## 7. Plan de bloques

Ver [[BLOCKS]] para el orden completo, dependencias y estado de cada bloque.

## 8. Checklist de aprobación

- [x] §4: cada campo nuevo declara Valor o Referencia
- [x] §6 cubre toda acción visible al usuario descrita en §1/§5 para el alcance de esta fase
- [x] Nombres consistentes con [[../../shared/GLOSSARY]]
- [x] No hay una feature existente que ya cubra esto (primera feature del vault)

> Aprobado — `BLOCKS.md` y las tarjetas de bloque ya existen y los dos primeros bloques de API están
> en `ready`.
