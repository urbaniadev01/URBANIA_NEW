---
tipo: feature
proyecto: shared
feature: PROPIEDADES
estado_diseño: approved
actualizado: 2026-07-08
---

# Feature: PROPIEDADES

## 1. Resumen y motivación

PROPIEDADES gestiona la estructura física de un condominio: condominios, torres, unidades, tipos de
propiedad, estados de propiedad, y coeficientes. Es la feature 1.1 del MVP (Fase 1), la primera con
datos de negocio reales después de AUTH. Sin esto, DIRECTORIO, COBRANZA y PORTERIA no pueden empezar.

## 2. Capas afectadas

- [x] API (origen del contrato)
- [x] Web
- [ ] App — diferido, ver [[../../app/APP_DEFERRED]]

## 3. Relación con otras features

- Depende de: [[../AUTH/PANORAMA]] — identidad vía JWT, tenant vía `organization_id`, RBAC vía
  `role_assignments`.
- Es consumido por:
  - [[../DIRECTORIO/PANORAMA]] — usa `properties.id` para `property_occupants`.
  - [[../COBRANZA/PANORAMA]] — usa `property_coefficients` para facturación.
  - [[../PORTERIA/PANORAMA]] — usa `properties.id` para asociar visitas/paquetes a unidades.
- **Explícitamente fuera de esta feature:** los ocupantes (`property_occupants`) pertenecen a
  DIRECTORIO. PROPIEDADES solo provee `properties.id` que DIRECTORIO referenciará.

## 4. Modelo de datos

### Decisiones de diseño resueltas (peer review)

1. **Torres como entidad separada** (tabla `towers`), no como atributo de `properties`.
   Justificación: RBAC scoping a nivel `tower` (ADR-001), y no todos los condominios tienen torres
   (conjuntos de casas, edificios únicos).

2. **Coeficientes en tabla separada con temporalidad** (`property_coefficients` con
   `vigente_desde`/`vigente_hasta`). Justificación: los coeficientes cambian en el tiempo (reformas,
   recalculo de áreas); COBRANZA necesita saber qué coeficiente regía en cada periodo.

3. **Catálogos con `organization_id` nullable**: sistema (null) + personalizados por tenant. Evita
   que 500 tenants creen los mismos 5 tipos básicos.

4. **Ocupantes (`property_occupants`) NO están en esta feature** — pertenecen a DIRECTORIO.
   PROPIEDADES solo provee `properties.id` que DIRECTORIO referenciará.

5. **Exposición de datos diferenciada por rol**: Admin ve `area_m2` y coeficiente solo en detalle,
   NO en listado. Residente solo ve su unidad (derivado de `property_occupants`, no de
   `role_assignment`). Endpoint de listado denegado para residentes (403). 403 y 404 se unifican
   para prevenir enumeración de condominios.

### Tablas nuevas (6)

Convenciones de columnas (UUID v7, soft delete, naming de FKs): [[../../shared/DATA_MODEL]] §1.
Convención de auditoría (`created_by`/`updated_by`): [[../../shared/DATA_MODEL]] §1-bis — vigente
para toda tabla nueva a partir de esta feature (no retroactiva a las tablas ya `done` de AUTH).

| Entidad | Nueva/Existente | Campo | Valor/Referencia | Notas |
|---|---|---|---|---|
| `condominiums` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `organization_id` | Referencia (`→ organizations.id`) | Tenant raíz |
| | | `nombre` | Valor (text) | NOT NULL, UNIQUE(organization_id, nombre) |
| | | `direccion` | Valor (text, nullable) | |
| | | `nit` | Valor (text, nullable) | NIT persona jurídica |
| | | `created_by` | Referencia (`→ users.id`, nullable) | Autoría de creación (R-11) |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | Autoría de última modificación (R-11) |
| | | `created_at` | Valor (timestamptz) | Automático |
| | | `updated_at` | Valor (timestamptz) | Automático |
| | | `deleted_at` | Valor (timestamptz, nullable) | Soft delete |
| `towers` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL, inmutable |
| | | `nombre` | Valor (text) | NOT NULL, UNIQUE(condominium_id, nombre) |
| | | `created_by` | Referencia (`→ users.id`, nullable) | Autoría de creación (R-11) |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | Autoría de última modificación (R-11) |
| | | `created_at` | Valor (timestamptz) | Automático |
| | | `updated_at` | Valor (timestamptz) | Automático |
| | | `deleted_at` | Valor (timestamptz, nullable) | Soft delete |
| `property_types` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `organization_id` | Referencia (`→ organizations.id`, nullable) | NULL = sistema, NOT NULL = personalizado |
| | | `nombre` | Valor (text) | NOT NULL |
| | | `descripcion` | Valor (text, nullable) | |
| | | `created_by` | Referencia (`→ users.id`, nullable) | NULL en catálogos de sistema (seed) |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | NULL en catálogos de sistema (seed) |
| | | `created_at` | Valor (timestamptz) | Automático |
| | | `updated_at` | Valor (timestamptz) | Automático |
| | | `deleted_at` | Valor (timestamptz, nullable) | Soft delete |
| `property_statuses` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `organization_id` | Referencia (`→ organizations.id`, nullable) | Mismo patrón que property_types |
| | | `nombre` | Valor (text) | NOT NULL |
| | | `descripcion` | Valor (text, nullable) | |
| | | `created_by` | Referencia (`→ users.id`, nullable) | NULL en catálogos de sistema (seed) |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | NULL en catálogos de sistema (seed) |
| | | `created_at` | Valor (timestamptz) | Automático |
| | | `updated_at` | Valor (timestamptz) | Automático |
| | | `deleted_at` | Valor (timestamptz, nullable) | Soft delete |
| `properties` | Nueva | `id` | Valor (UUID v7 PK) | La "unidad" del dominio |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL, inmutable |
| | | `tower_id` | Referencia (`→ towers.id`, nullable) | NULL = condominio sin torres |
| | | `property_type_id` | Referencia (`→ property_types.id`) | NOT NULL |
| | | `property_status_id` | Referencia (`→ property_statuses.id`) | NOT NULL |
| | | `codigo` | Valor (text) | NOT NULL, UNIQUE(condominium_id, codigo). Ej: "101", "A-201" |
| | | `piso` | Valor (int, nullable) | |
| | | `area_m2` | Valor (decimal(10,2), nullable) | Dato sensible — solo en detalle |
| | | `created_by` | Referencia (`→ users.id`, nullable) | Autoría de creación (R-11) |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | Autoría de última modificación (R-11) |
| | | `created_at` | Valor (timestamptz) | Automático |
| | | `updated_at` | Valor (timestamptz) | Automático |
| | | `deleted_at` | Valor (timestamptz, nullable) | Soft delete |
| `property_coefficients` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `property_id` | Referencia (`→ properties.id`) | NOT NULL |
| | | `tipo` | Valor (text, enum cerrado — ver R-06-bis) | NOT NULL. Set cerrado: `copropiedad`, `parqueadero`, `deposito`, `mantenimiento`. Validado por CHECK constraint en BD + FormRequest en API (422 `COEFFICIENT_INVALID_TYPE` si no pertenece al set) |
| | | `valor` | Valor (decimal(5,4)) | NOT NULL. Rango 0-1 (fracción). UNIQUE(property_id, tipo) WHERE vigente_hasta IS NULL |
| | | `vigente_desde` | Valor (date) | NOT NULL |
| | | `vigente_hasta` | Valor (date, nullable) | NULL = vigente. Se cierra al crear nuevo del mismo tipo |
| | | `created_by` | Referencia (`→ users.id`, nullable) | Autoría de creación (R-11) |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | Autoría de última modificación (R-11) |
| | | `created_at` | Valor (timestamptz) | Automático |
| | | `updated_at` | Valor (timestamptz) | Automático |
| | | `deleted_at` | Valor (timestamptz, nullable) | Soft delete |

> **Decisión (reemplaza punto ciego "audit trail"):** se agregan `created_by`/`updated_by` (FK
> nullable a `users.id`) a las 6 tablas de esta feature. Nullable porque los catálogos de sistema
> (seed de B01) no tienen un usuario autor. Esta decisión es **hacia adelante**: no se retrofitea a
> las tablas ya `done` de AUTH (`organizations`, `users`, `contacts`, `invitations`, `roles`,
> `permissions`, `role_assignments`, `refresh_tokens`, `user_mfa`, `password_reset_tokens`) — eso
> sería un bloque de migración aparte, fuera de alcance de esta feature.
>
> **Decisión (reemplaza punto ciego "tipo de coeficiente: ¿libre o catálogo?"):** se mantiene `tipo`
> como columna de texto con un **enum cerrado**, no una tabla de catálogo nueva
> (`coefficient_types`). A diferencia de `property_types`/`property_statuses` — que sí necesitan ser
> personalizables por tenant (R-08) — los tipos de coeficiente son un concepto financiero fijo del
> dominio, no algo que cada administradora deba poder crear libremente; una tabla+CRUD nueva sería
> sobre-ingeniería para un set de 4 valores que cambia por decisión de negocio, no por tenant. El set
> cerrado y su validación quedan fijados en R-06-bis.

## 5. Reglas de negocio globales

- **R-01 — Jerarquía:** organization → condominium → tower (opcional) → property. Una propiedad no
  existe sin condominio. Una torre no existe sin condominio.
- **R-02 — Unicidad:** código de unidad único dentro del condominio. Nombre de torre único dentro
  del condominio. Nombre de condominio único dentro de la organización. Tipo/estado de propiedad
  único dentro de la organización (o a nivel sistema si es catálogo base).
- **R-03 — No eliminar con hijos:** no se puede soft-delete un condominio con torres/unidades
  activas. No se puede eliminar una torre con unidades activas. No se puede eliminar un tipo/estado
  referenciado por propiedades activas.
- **R-04 — Soft delete universal:** ninguna entidad se elimina físicamente.
- **R-05 — Coeficiente vigente único:** solo un coeficiente por propiedad+tipo con
  `vigente_hasta IS NULL`. Crear uno nuevo cierra automáticamente el anterior.
- **R-06 — Suma de coeficientes:** la suma de coeficientes de copropiedad de un condominio debe ser
  1.0 (100%). Validación en capa de aplicación (no constraint de BD en esta fase). No bloquea el
  guardado si no suma 100% — la respuesta `200` incluye un `warnings[]` con `code:
  COEFFICIENT_SUM_MISMATCH` (formato definido en `api/API_CONTRACT.md` §4-bis). COBRANZA usará
  valores normalizados.
- **R-06-bis — Set cerrado de `tipo` de coeficiente:** los únicos valores válidos para
  `property_coefficients.tipo` son `copropiedad`, `parqueadero`, `deposito`, `mantenimiento`.
  Enforced con CHECK constraint en BD (defensa en profundidad) y con validación en el FormRequest de
  la API, que responde `422 COEFFICIENT_INVALID_TYPE` ante cualquier otro valor. Solo los
  coeficientes de tipo `copropiedad` participan de la validación de suma de R-06 — los demás tipos
  (parqueadero, depósito, mantenimiento) son independientes por unidad y no necesitan sumar 100% a
  nivel de condominio.
- **R-07 — Inmutabilidad de pertenencia:** `condominium_id` en `properties` y `towers` es inmutable
  una vez creado. Una unidad no se "muda" de condominio.
- **R-08 — Catálogos del sistema inmutables:** tipos/estados con `organization_id = NULL` no pueden
  ser editados ni eliminados por tenants.
- **R-09 — Tenant isolation:** toda query scopea por `organization_id` (JWT → RLS). Un usuario
  nunca ve datos de otra organización.
- **R-09-bis — Aislamiento por scope de staff:** un usuario con `role_assignment.scope_type ∈
  {condominium, tower}` (ADR-001 §2/§4) solo accede a los recursos dentro de su(s) scope(s)
  asignado(s), incluso dentro de la misma organización — ej. un "administrador de conjunto" con
  scope `condominium: A` no puede ver ni modificar el condominio `B` de la misma organización, y un
  "vigilante" con scope `tower: X` no gestiona otras torres del mismo condominio. Aplica el mismo
  criterio anti-enumeración de R-10 (403/404 unificados). Este es el motivo original por el que
  `towers` existe como entidad separada — debe probarse explícitamente en los bloques B03-B05, no
  solo el caso binario admin-de-toda-la-org / residente. Excepción: el endpoint `tree` y la gestión
  de coeficientes (datos financieros, R-10) requieren scope `condominium` u `organization` — un
  scope `tower` (ej. vigilante) es insuficiente para verlos, igual que un residente.
- **R-10 — Exposición de datos sensibles:** `area_m2` y coeficiente solo se exponen en endpoint de
  detalle, no en listados. Residente solo ve su unidad (derivado de `property_occupants`). 403 y
  404 se unifican para prevenir enumeración de condominios.
- **R-11 — Auditoría de autoría:** toda escritura (`store`/`update`) en las tablas de esta feature
  setea `created_by`/`updated_by` con el `user_id` del actor autenticado (ADR-001 §3: actor =
  `user_id`, nunca `contact_id`). Excepción: los registros de catálogo de sistema sembrados por
  seeders (B01) quedan con ambos campos en `NULL`.

## 6. Mapeo de acciones a endpoints (alto nivel)

El detalle de request/response vive en `api/endpoints/PROPIEDADES.md` — aquí solo el mapeo.

### Catálogos

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar tipos de propiedad | GET | `/property-types` |
| Crear tipo de propiedad | POST | `/property-types` |
| Editar tipo de propiedad | PATCH | `/property-types/{id}` |
| Eliminar tipo de propiedad | DELETE | `/property-types/{id}` |
| Listar estados de propiedad | GET | `/property-statuses` |
| Crear estado de propiedad | POST | `/property-statuses` |
| Editar estado de propiedad | PATCH | `/property-statuses/{id}` |
| Eliminar estado de propiedad | DELETE | `/property-statuses/{id}` |

### Condominios

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar condominios | GET | `/condominiums` |
| Ver condominio | GET | `/condominiums/{id}` |
| Crear condominio | POST | `/condominiums` |
| Editar condominio | PATCH | `/condominiums/{id}` |
| Eliminar condominio | DELETE | `/condominiums/{id}` |

### Torres

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar torres de condominio | GET | `/condominiums/{id}/towers` |
| Ver torre | GET | `/towers/{id}` |
| Crear torre | POST | `/condominiums/{id}/towers` |
| Editar torre | PATCH | `/towers/{id}` |
| Eliminar torre | DELETE | `/towers/{id}` |

### Unidades

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar unidades de condominio | GET | `/condominiums/{id}/properties` (?tower_id=&type_id=&status_id=&search=) |
| Ver unidad | GET | `/properties/{id}` |
| Crear unidad | POST | `/condominiums/{id}/properties` |
| Editar unidad | PATCH | `/properties/{id}` |
| Eliminar unidad | DELETE | `/properties/{id}` |

### Coeficientes

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar coeficientes de unidad | GET | `/properties/{id}/coefficients` |
| Gestión masiva de coeficientes | PATCH | `/condominiums/{id}/coefficients` (body: `[{property_id, tipo, valor}]`, atómico) |

### Conveniencia

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Árbol de condominio | GET | `/condominiums/{id}/tree` |

## 7. Plan de bloques

Una vez `estado_diseño: approved`, el detalle de bloques vive en `BLOCKS.md` (mismo directorio que
este panorama).

## 8. Checklist de aprobación (gate)

- [ ] §4: cada campo nuevo declara Valor o Referencia
- [ ] §6 cubre toda acción visible al usuario descrita en §1/§5
- [ ] Nombres de campos y entidades consistentes con [[../../shared/GLOSSARY]]
- [ ] No hay una feature existente en `features/` que ya cubra esto (revisar `_state/BOARD.md`)
- [x] Nuevos términos de dominio agregados a [[../../shared/GLOSSARY]] (Torre, Coeficiente, Catálogo de sistema/personalizado, Árbol de condominio — 2026-07-08)

> Al marcar todos los ítems, este documento puede pasar a `estado_diseño: approved` y recién ahí se
> crea `BLOCKS.md`.

## X. Veredicto del Design Council

> Este panorama fue producido por el protocolo LLM Council de 3 fases:
> 1. **Divergencia** — diseño independiente por 3 subagentes (arquitectura, UX, seguridad)
> 2. **Peer Review** — revisión anonimizada y ranking
> 3. **Síntesis** — este documento unificado

### Puntos de acuerdo unánime

1. **Jerarquía organization → condominium → tower → property**: todos los diseños convergieron en
   este modelo anidado como la estructura natural del dominio de propiedad horizontal colombiano.
2. **Separación de catálogos**: `property_types` y `property_statuses` como entidades independientes
   con soporte para catálogos de sistema (null `organization_id`) y personalizados por tenant.
3. **Soft delete como estándar**: ninguna entidad se elimina físicamente; `deleted_at` en todas las
   tablas.
4. **Tenant isolation vía `organization_id` + RLS**: alineado con ADR-001, sin excepciones.
5. **Coeficientes con temporalidad**: necesario para que COBRANZA pueda facturar correctamente en
   cualquier periodo, cubriendo cambios históricos.

### Divergencias resueltas

| Tema | Posturas | Resolución adoptada |
|---|---|---|
| Torres: ¿entidad o atributo? | A: entidad separada (`towers`). B: columna `torre` en `properties`. | Entidad separada. RBAC scoping a nivel torre (ADR-001) + no todos los condominios tienen torres. |
| Coeficientes: ¿temporalidad o snapshot? | A: tabla separada con `vigente_desde`/`vigente_hasta`. B: columna inline en `properties`. | Tabla separada con temporalidad. COBRANZA necesita historial de coeficientes por periodo. |
| Ocupantes: ¿dentro o fuera de PROPIEDADES? | A: fuera (pertenece a DIRECTORIO). B: dentro (incluir `property_occupants`). | Fuera de PROPIEDADES. Pertenece al bounded context de DIRECTORIO. PROPIEDADES solo expone `properties.id`. |
| Exposición de datos por rol | A: admin ve todo; residente solo su unidad. B: admin ve todo en listado también. | Admin: área/coeficiente solo en detalle, no en listado. Residente: solo su unidad, 403 en listado. 403/404 unificado para anti-enumeración. |
| Catálogos: ¿globales o por tenant? | A: sistema + personalizados. B: siempre por tenant (sin compartir). | Sistema (null) + personalizados. Evita duplicación de catálogos base idénticos entre tenants. |

### Puntos ciegos identificados — estado tras revisión previa a `PROPIEDADES-B01`

1. **Audit trail — ✅ RESUELTO.** Se agregan `created_by`/`updated_by` a las 6 tablas (ver §4 y
   R-11). No se crea tabla de auditoría separada — fuera de alcance de esta feature; si se necesita
   un historial de cambios completo (no solo "quién/cuándo"), es una feature aparte.
2. **Concurrencia en coeficientes — diferido, deuda técnica explícita.** El PATCH masivo atómico
   (transacción única) mitiga el caso principal. No se implementa optimistic locking en el MVP.
   Documentado también en la tarjeta `PROPIEDADES-B05`. Si se detectan conflictos reales en
   producción, se resuelve en un bloque futuro — no bloquea esta feature.
3. **Paginación y filtros — ✅ RESUELTO, no era un punto ciego real.** Ya existe una convención
   global cursor-based en `api/API_CONTRACT.md` §4 (`?cursor=...&limit=...`, envelope `{ data,
   meta.next_cursor }`); los bloques B04/B05 la heredan sin necesidad de decidir nada nuevo. Los
   filtros combinables (`tower_id`, `type_id`, `status_id`, `search`) se aplican como query params
   adicionales sobre ese mismo endpoint paginado.
4. **Batch import de unidades — diferido, explícitamente fuera del MVP.** Confirmado como fuera de
   alcance en `PROPIEDADES-B04` (ver "No incluye"). Si se vuelve un requisito, es un bloque nuevo
   (`PROPIEDADES-B10` o similar) que depende de `PROPIEDADES-B04` `done` — no se abre bloque
   `backlog` hoy porque no hay fecha ni prioridad confirmada por el usuario.
5. **Caché de catálogos — diferido, no bloquea el MVP.** `property_types`/`property_statuses` se
   sirven sin caché en la primera versión; es una optimización de performance, no un requisito
   funcional. Revisar si `GET /property-types`/`GET /property-statuses` se vuelven un cuello de
   botella medido en producción antes de invertir en ETag/Redis.
6. **Política de purga de soft-deletes — diferido, decisión de producto pendiente.** No se
   implementa purga física en esta feature. Requiere una decisión de retención de datos que afecta a
   todo el sistema (no solo PROPIEDADES) — se documentará como ADR aparte si/cuando se defina una
   política de retención global, dado su impacto en COBRANZA (facturación histórica).

### Recomendación del council

El diseño es sólido: las decisiones de arquitectura están alineadas con ADR-001 (multi-tenancy,
RBAC con scope, actor/party), la separación de bounded contexts es clara (ocupantes en DIRECTORIO, no
aquí), y el modelo de datos cubre el dominio de propiedad horizontal colombiano sin
sobre-ingeniería. Se recomienda aprobación con los puntos ciegos documentados arriba como deuda
técnica explícita a resolver durante la implementación de los bloques.
