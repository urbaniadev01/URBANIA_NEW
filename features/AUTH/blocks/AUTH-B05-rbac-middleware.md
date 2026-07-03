---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B05
proyectos: [api]
estado: backlog
depende_de: [AUTH-B02]
contrato: null
actualizado: 2026-07-03
---

# AUTH-B05 — Middleware de autorización RBAC

## Objetivo

Implementar el gate de autorización real descrito en
[[../../../shared/adr/ADR-001-actor-party]] §2: tablas `roles`/`permissions`/`role_assignments`, un
`Gate` que resuelve permisos efectivos por request (cacheado), y aplicarlo sobre al menos un
endpoint de ejemplo protegido. Este bloque es el mecanismo directo contra el segundo hueco de
seguridad de la auditoría que motivó este vault: un gate de autorización basado en una columna de
rol legacy en vez del RBAC real.

## Alcance

**Incluye:**
- Migraciones: `roles`, `permissions`, `role_assignments` (con `scope_type`/`scope_id`).
- Módulo `Authorization` (separado de `Auth`, ver `api/API_ARCHITECTURE.md` §5).
- `Gate::can('recurso.accion', $scope)` resuelto contra `role_assignments` + `permissions`, con
  cache (invalidado al cambiar una asignación).
- Middleware HTTP que aplica el gate a una ruta protegida de ejemplo (usar un endpoint simple, ej.
  "ver mi propio perfil" vs. una acción administrativa de ejemplo).

**No incluye:**
- Catálogo completo de permisos del sistema (~14 roles previstos) — solo lo mínimo para demostrar
  el mecanismo funcionando; el catálogo completo se llena a medida que cada feature futura declara
  qué permisos necesita.
- UI de gestión de roles (Web) — feature futura.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `role_assignment` que otorga el permiso requerido en el scope correcto | Acceder al endpoint protegido de ejemplo | `200` |
| 2 | Usuario autenticado sin ese `role_assignment` | Acceder al endpoint protegido | `403 PERMISSION_DENIED` |
| 3 | Usuario con el permiso pero en un `scope` distinto (otra organización/condominio) | Acceder al endpoint protegido | `403 PERMISSION_DENIED` — confirma que el scope se verifica, no solo la existencia del permiso |
| 4 | Se revoca el `role_assignment` de un usuario con sesión activa | Reintentar el endpoint protegido inmediatamente después | `403 PERMISSION_DENIED` — confirma que el cache se invalida, no solo que expira por TTL |
| 5 | Un usuario intenta acceder usando cualquier columna/atributo legacy de "rol" que no pase por `role_assignments` | Acceder al endpoint protegido | Rechazado — el gate no debe tener ninguna ruta alterna que dependa de otra fuente distinta a RBAC |

> El caso 5 es explícito porque es exactamente el patrón que falló en el vault anterior: un segundo
> camino de autorización (columna legacy) que coexistía con el sistema RBAC nuevo y ganaba por
> accidente.

## Definition of Done

- [ ] `composer ci` ejecutado — salida pegada.
- [ ] Test por cada fila de la tabla (5 casos), incluyendo el 4 (invalidación de cache) y el 5
      (ausencia de ruta alterna de autorización).
- [ ] Verificación funcional real de los casos 1, 2 y 3 pegada.
- [ ] `api/API_DATABASE.md` — tablas `roles`, `permissions`, `role_assignments` documentadas.
- [ ] `api/API_ARCHITECTURE.md` §5 — contexto `Authorization` agregado a la tabla de bounded
      contexts.

## Evidencia

_Vacío._

## Notas

_Vacío._
