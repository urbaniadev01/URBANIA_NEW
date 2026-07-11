---
tipo: feature
proyecto: shared
feature: PORTERIA
estado_diseño: approved
actualizado: 2026-07-11
---

# Feature: PORTERIA

## 1. Resumen y motivación

PORTERIA (básico) le da al vigilante una consola funcional real desde el día 1: registrar el
ingreso/salida de visitantes (minuta digital) y recibir/entregar correspondencia — el reemplazo
digital de la libreta de papel. Es la feature 1.5 del MVP (Fase 1), pensada explícitamente para que
el vigilante la use en la demo al cliente, no una maqueta.

Alcance deliberadamente acotado — la versión completa (Fase 2, 2.5) agrega preautorización por
QR/PIN, rondas con checkpoints, botón de pánico, parqueo de visitantes, listas de acceso recurrente y
turnos de vigilancia con minuta de novedades. Esta versión resuelve un solo caso: "el vigilante
anota quién entra/sale y qué paquete llega/se entrega", sin esa capa avanzada.

**Hallazgo de esta sesión:** el rol `vigilante` (mencionado en la documentación como ejemplo de
scope `tower`/`condominium`, ver `features/PROPIEDADES/PANORAMA.md`, `web/WEB_ARCHITECTURE.md`) no
existe todavía en el catálogo real de roles — `RbacDemoSeeder.php` solo siembra `admin`, `manager` y
`resident` (`AUTH-B05` fue explícito: "solo lo mínimo para demostrar el mecanismo", no los ~14 roles
previstos). El motor RBAC (scope_type, role_assignments) ya soporta cualquier rol nuevo sin cambios
de esquema — este feature es el primero que de verdad necesita `vigilante`, así que su bloque
fundacional lo crea (ver §5, R-PORT-05). No es una corrección de un diseño existente (como el caso de
`contacts` en `DIRECTORIO`) — es simplemente el primer consumidor real de un rol que hasta ahora solo
se citaba como ejemplo.

## 2. Capas afectadas

- [x] API (origen del contrato)
- [x] Web
- [ ] App — diferido, ver [[../../app/APP_DEFERRED]]. La consola de vigilante en app nativa (research:
      "variante de vigilante recomendada") es candidata natural cuando el track de App arranque, pero
      no se planea aquí.

## 3. Relación con otras features

- Depende de: [[../AUTH/PANORAMA]] — identidad vía JWT, tenant vía `organization_id`, RBAC vía
  `role_assignments` (motor ya `done` desde `AUTH-B05`; el rol `vigilante` en sí lo crea este
  feature, ver §1). Depende de [[../PROPIEDADES/PANORAMA]] — necesita `condominiums.id` y
  `properties.id` (`PROPIEDADES-B03`/`B04`, ambos `done`). Depende de [[../DIRECTORIO/PANORAMA]]
  para `entregado_a` (`contacts.id`, tabla ya existe desde `AUTH-B01`) — el bloque Web de
  correspondencia además necesita el endpoint de ocupantes por unidad
  (`DIRECTORIO-B04`, hoy `backlog`) para que el vigilante seleccione a quién le entrega el paquete
  de una lista real, no texto libre.
- Es consumido por: ninguna feature todavía. `PORTAL_RESIDENTE` (1.6) **no** consume esta feature en
  su versión básica — confirmado explícitamente: la consola es 100% staff por ahora (vigilante,
  admin, manager); la vista de residente ("mi correspondencia", "minuta de mi unidad") queda para
  cuando se diseñe `PORTERIA` completo (Fase 2) o `PORTAL_RESIDENTE`, lo que llegue primero.
- **Explícitamente fuera de esta feature:** preautorización QR/PIN (`access_authorizations`), rondas
  y checkpoints (`patrol_routes`/`patrol_checkpoints`/`patrol_rounds`/`checkpoint_scans`), botón de
  pánico (`panic_alerts`), parqueo de visitantes (`visitor_parking`), listas de acceso recurrente
  (`access_blocklist`), turnos de vigilancia y minuta de novedades (`guard_shifts`/
  `shift_log_entries`) — todo eso es `PORTERIA` (completo), feature 2.5 de Fase 2. Foto de evidencia
  del paquete también queda fuera — requiere infraestructura de almacenamiento de archivos que no
  existe todavía en el proyecto.

## 4. Modelo de datos

Convenciones de columnas: [[../../shared/DATA_MODEL]] §1. Auditoría con campos semánticos en vez de
`created_by`/`updated_by` genéricos — mismo criterio que `billing_runs.ejecutado_por`/
`peace_certificates.emitido_por` de [[../COBRANZA/PANORAMA]] §4 (decisión 4 de su Design Council):
cuando existe un nombre de acción específico y significativo, reemplaza al genérico, no lo duplica.

| Entidad | Nueva/Existente | Campo | Valor/Referencia | Notas |
|---|---|---|---|---|
| `visits` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL, inmutable |
| | | `property_id` | Referencia (`→ properties.id`) | NOT NULL, inmutable. Unidad destino |
| | | `visitante_nombre` | Valor (text) | NOT NULL |
| | | `visitante_documento` | Valor (text) | NOT NULL |
| | | `vehiculo_placa` | Valor (text, nullable) | Opcional |
| | | `ingreso_at` | Valor (timestamptz) | NOT NULL, se llena al crear (ahora) |
| | | `salida_at` | Valor (timestamptz, nullable) | `NULL` = visita activa (R-PORT-04, estado derivado) |
| | | `registrado_por` | Referencia (`→ users.id`) | NOT NULL. Reemplaza `created_by` — quien registra el ingreso |
| | | `cerrado_por` | Referencia (`→ users.id`, nullable) | Quien registra la salida (puede ser otro turno) |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar. `deleted_at` sin endpoint expuesto (R-PORT-07) |
| `packages` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL, inmutable |
| | | `property_id` | Referencia (`→ properties.id`) | NOT NULL, inmutable. Unidad destino |
| | | `transportadora` | Valor (text, nullable) | No siempre se identifica |
| | | `descripcion` | Valor (text) | NOT NULL |
| | | `recibido_at` | Valor (timestamptz) | NOT NULL, se llena al crear |
| | | `recibido_por` | Referencia (`→ users.id`) | NOT NULL. Reemplaza `created_by` |
| | | `entregado_at` | Valor (timestamptz, nullable) | `NULL` = pendiente de reclamar (R-PORT-04) |
| | | `entregado_por` | Referencia (`→ users.id`, nullable) | Staff que hace la entrega |
| | | `entregado_a` | Referencia (`→ contacts.id`, nullable) | Residente que retira. NOT NULL cuando `entregado_at` no es NULL |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar. `deleted_at` sin endpoint expuesto (R-PORT-07) |

## 5. Reglas de negocio globales

- **R-PORT-01 — Tenant isolation:** toda query de `visits`/`packages` scopea transitivamente por
  `condominium_id → condominiums.organization_id` (ADR-001 §1).
- **R-PORT-02 — Aislamiento por scope de staff:** mismo patrón R-09-bis de `PROPIEDADES` — un
  usuario con `role_assignment.scope_type ∈ {condominium, tower}` solo opera sobre su(s)
  condominio(s)/torre(s) asignado(s). Un vigilante con scope `tower` no ve visitas/paquetes de otras
  torres del mismo condominio.
- **R-PORT-03 — Un solo permiso para todo el feature:** `porteria.manage` (nuevo) cubre lectura y
  escritura de `visits`/`packages` — sin separar `ver`/`gestionar` como sí hace `COBRANZA`, porque
  esta feature es 100% staff (confirmado con el usuario: sin acceso residente todavía) y no hay un
  caso real de "staff que solo lee sin poder registrar". Asignado a `vigilante` (rol nuevo, ver §1),
  `admin` y `manager`.
- **R-PORT-04 — Estado derivado, nunca almacenado:** una visita es "activa" si `salida_at IS NULL`,
  "cerrada" en caso contrario; un paquete es "pendiente" si `entregado_at IS NULL`, "entregado" en
  caso contrario. Mismo criterio que `invoices.estado` de `COBRANZA` — se deriva en lectura, no se
  guarda una columna `estado` separada que pueda desincronizarse.
- **R-PORT-05 — Creación del rol `vigilante`:** este feature crea el rol `vigilante` en
  `RbacDemoSeeder.php` (no existía, ver hallazgo en §1) con el permiso `porteria.manage`. El motor
  RBAC no cambia — es solo una fila nueva de catálogo, igual que crear cualquier rol personalizado.
- **R-PORT-06 — Auditoría con campos semánticos:** `registrado_por`/`cerrado_por` (visits),
  `recibido_por`/`entregado_por` (packages) reemplazan a `created_by`/`updated_by` genéricos — mismo
  criterio que `billing_runs.ejecutado_por` de `COBRANZA` (ver §4).
- **R-PORT-07 — Sin eliminación:** no existe endpoint `DELETE` para `visits` ni `packages` — son
  registro legal (minuta de accesos, Ley 675/Habeas Data), se corrigen (`PATCH`, ej. typo en el
  documento del visitante) pero nunca se borran. La columna `deleted_at` existe por la convención
  estándar de `shared/DATA_MODEL.md` §1 pero ningún endpoint la usa en esta feature.
- **R-PORT-08 — Inmutabilidad de pertenencia:** `condominium_id`/`property_id` son inmutables tras
  la creación (mismo R-07 de `PROPIEDADES`) — no existe "mover" una visita/paquete a otra unidad.
- **R-PORT-09 — Entrega exige destinatario real:** `entregado_a` debe ser un `contact_id` que sea
  ocupante activo (`property_occupants`) de la unidad del paquete — no cualquier contacto de la
  organización. Si la persona que retira no está registrada como ocupante, se registra primero en
  `DIRECTORIO` (fuera de esta feature) antes de poder completar la entrega.

## 6. Mapeo de acciones a endpoints (alto nivel)

El detalle de request/response vive en `api/endpoints/PORTERIA.md` — aquí solo el mapeo.

### Visitas

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar visitas del condominio (`?property_id=&estado=activa\|cerrada`) | GET | `/condominiums/{id}/visits` |
| Registrar ingreso de un visitante | POST | `/condominiums/{id}/visits` |
| Corregir datos de una visita activa (nombre/documento/placa) | PATCH | `/visits/{id}` |
| Registrar salida | PATCH | `/visits/{id}/salida` |

### Correspondencia

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| Listar paquetes del condominio (`?property_id=&estado=pendiente\|entregado`) | GET | `/condominiums/{id}/packages` |
| Registrar recepción de un paquete | POST | `/condominiums/{id}/packages` |
| Corregir datos de un paquete pendiente (transportadora/descripción) | PATCH | `/packages/{id}` |
| Registrar entrega (requiere `entregado_a`) | PATCH | `/packages/{id}/entrega` |

## 7. UI/UX

**Tier:** Estándar — dos pantallas de lista + formulario/acción, sin visualización de datos ni
layout no convencional.

### 7.1 Pantallas afectadas

| Pantalla | Tipo | Ruta | Nueva/Existente |
|---|---|---|---|
| Visitantes | Página | `/porteria/visitantes` | Nueva |
| Registrar ingreso | Sheet | (dentro de la página anterior) | Nueva |
| Correspondencia | Página | `/porteria/correspondencia` | Nueva |
| Registrar paquete | Sheet | (dentro de la página anterior) | Nueva |

### 7.2 Componentes de librería usados

`Table` (lista de visitas/paquetes — volumen esperado más alto que `COMUNICACIONES` por tratarse de
un registro diario continuo; se usa el componente base `table` de shadcn/ui, sin adoptar todavía la
convención única de `DataTable` con filtros/exportación avanzada, que sigue pendiente de decidirse en
la primera pantalla que la necesite de verdad), `Sheet` (registrar ingreso/paquete), `Badge` (estado
activa/cerrada, pendiente/entregado), `Select` o `Combobox` (selector de unidad destino en
Visitantes; selector de ocupante destinatario en Correspondencia, poblado desde
`GET /properties/{id}/occupants` de `DIRECTORIO-B04`), `Button`, `Input`, `AlertDialog` (confirmar
registrar salida/entrega — no son destructivas pero sí irreversibles en la práctica, mismo criterio
de confirmación).

### 7.3 Estados de la vista

| Pantalla | Loading | Vacío | Error | Éxito |
|---|---|---|---|---|
| Visitantes | Skeleton de tabla | "No hay visitas registradas hoy" + CTA "Registrar ingreso" | Toast + reintento | Toast de confirmación al registrar ingreso/salida |
| Correspondencia | Skeleton de tabla | "No hay paquetes registrados" + CTA "Registrar paquete" | Toast + reintento | Toast de confirmación al registrar recepción/entrega |

### 7.4 Navegación

Nueva entrada de sidebar "Portería" → "Visitantes" y "Correspondencia", visible solo a usuarios con
`porteria.manage` (a diferencia de `COMUNICACIONES`, esta feature no tiene lectura abierta — ver
R-PORT-03).

## 8. Plan de bloques

Una vez `estado_diseño: approved`, el detalle de bloques vive en `BLOCKS.md` (mismo directorio que
este panorama).

## 9. Checklist de aprobación (gate)

- [x] §4 (modelo de datos): cada campo nuevo declara Valor o Referencia explícitamente
- [x] §6 (mapeo de acciones a endpoints) cubre toda acción visible al usuario descrita en §1/§5
- [x] §7 (UI/UX) completa: pantallas, componentes y estados declarados (Tier Estándar, sin
      wireframe/responsive obligatorios)
- [x] Nombres de campos y entidades consistentes con `shared/GLOSSARY.md` — términos nuevos
      ("Visita", "Paquete", "Vigilante") agregados en esta misma sesión
- [x] No hay una feature existente en `features/` que ya cubra esto (revisada `_state/BOARD.md`) —
      confirmado, solo `AUTH`/`API_BOOTSTRAP`/`WEB_BOOTSTRAP`/`PROPIEDADES`/`DIRECTORIO`/`DASHBOARD`/
      `COBRANZA`/`COMUNICACIONES` existen

> Aprobado por el usuario vía revisión directa del panorama en la misma conversación en que se
> redactó este documento (2026-07-11) — el gate humano de `_system/03_LIFECYCLE.md` §3.

## 10. Análisis de diseño (Claude Code, no un Design Council multi-agente)

Panorama redactado por Claude Code en modo asesoría directa (mismo criterio que
[[../DIRECTORIO/PANORAMA]] §9 y [[../COMUNICACIONES/PANORAMA]] §10) — feature clasificada como
"Simple" (dos entidades chicas, cuatro reglas de estado derivado, sin catálogos ni cálculos), sin
correr el protocolo de 3 fases. Se usó `D:\Programacion\URBANIA_DEV_PLAN\PLAN_FASES_DESARROLLO.md`
(ítem 1.5 y su nota "Portería en Fase 1") como guía de alcance, y el research de pantallas/modelo de
datos externo (sección 12) para la versión completa a recortar. Cuatro decisiones de alcance se
confirmaron con el usuario antes de redactar: sin foto de paquete (evita infraestructura de archivos
nueva), dos páginas simples en vez de un tablero agregado, permiso de gestión abierto a
vigilante/admin/manager, y consola 100% staff sin vista de residente todavía. El hallazgo del rol
`vigilante` inexistente (§1) surgió de contrastar la afirmación del plan externo ("ya existe desde
AUTH-B05") contra `RbacDemoSeeder.php` real, mismo tipo de verificación que encontró el bug de
`contacts` en `DIRECTORIO`.
