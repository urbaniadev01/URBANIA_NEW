---
tipo: feature
proyecto: shared
feature: PORTAL_RESIDENTE
estado_diseño: approved
actualizado: 2026-07-11
---

# Feature: PORTAL_RESIDENTE

## 1. Resumen y motivación

PORTAL_RESIDENTE (mínimo) le da al residente, en su primer login, algo más útil que un dashboard
vacío: "cuánto debo" y "qué avisos hay". Es la feature 1.6 del MVP (Fase 1) — deliberadamente
mínima ("Sin tablas nuevas — solo lectura sobre lo anterior", tabla autoritativa de fases), no un
portal completo (sin "Mi unidad", sin descarga de paz y salvo, sin reservas — eso no está
roadmapeado todavía en ninguna fase visible del plan externo).

**Confirmado con el usuario:** esta feature **no introduce ninguna ruta ni layout nuevo**. Reutiliza
el Dashboard ya existente ([[../DASHBOARD/PANORAMA]]) — agrega 2 widgets nuevos al mismo Widget
Registry, visibles solo para residentes. Esto no es una decisión nueva de esta sesión: es exactamente
lo que `DASHBOARD/PANORAMA.md` §6 dejó preparado ("Widget futuro: Mi Unidad... este widget NO es
parte del MVP [de DASHBOARD]... se diseña ahora para que la arquitectura no lo bloquee") y lo que
`COBRANZA/PANORAMA.md` §3/§6 asumió al exponer `GET /me/invoices` ("agregará 'mi saldo'... ya
expuesto aquí"). El Design Council de `DASHBOARD` además **rechazó explícitamente** un endpoint de
agregación dedicado para residente (§10.3 de ese documento) — la composición 100% client-side ya es
la decisión vigente, esta feature solo la ejecuta.

## 2. Capas afectadas

- [x] Web — 2 widgets nuevos + la resolución del condominio propio del residente (ver §5,
      R-PORTAL-02).
- [ ] API — **cero endpoints nuevos.** Consume `GET /me/invoices` (ya expuesto por `COBRANZA`,
      `COBRANZA-B04`) y `GET /condominiums/{id}/announcements` (ya expuesto por `COMUNICACIONES`,
      `COMUNICACIONES-B01`). Ver R-PORTAL-02 sobre cómo se resuelve el `{id}` de condominio sin un
      endpoint nuevo.
- [ ] App — diferido, ver [[../../app/APP_DEFERRED]].

## 3. Relación con otras features

- Depende de: [[../DASHBOARD/PANORAMA]] — reutiliza el Widget Registry (`registerWidget`,
  `registerSidebarItem`, `getVisibleWidgets`) y el store `useActiveCondominium` construidos en
  `DASHBOARD-B01`/`DASHBOARD-B02`, ambos `done`. Depende de [[../COBRANZA/PANORAMA]] —
  `GET /me/invoices` (`COBRANZA-B04`, hoy `backlog`). Depende de [[../COMUNICACIONES/PANORAMA]] —
  `GET /condominiums/{id}/announcements` (`COMUNICACIONES-B01`, hoy `ready`). Depende de
  [[../DIRECTORIO/PANORAMA]] — `GET /me/contact` y `GET /contacts/{id}/properties`
  (`DIRECTORIO-B03`, hoy `backlog`) para resolver el condominio propio del residente (ver R-PORTAL-02).
- Es consumido por: ninguna feature todavía.
- **Relación con el widget "Mi Unidad" de `DASHBOARD`:** ese widget (datos de unidad + ocupantes +
  coeficiente) sigue sin construirse — es un concepto relacionado pero distinto de "mi saldo"/"mis
  avisos", y esta feature no lo reclama ni lo construye. Queda como deuda técnica de `DASHBOARD`,
  no de `PORTAL_RESIDENTE`.
- **Explícitamente fuera de esta feature:** detalle de factura individual, descarga de paz y salvo,
  reservas de amenidades, documentos del conjunto, "mi perfil" — todo eso pertenece a un portal
  completo que hoy no tiene fase asignada en el plan de fases externo. No se construye
  especulativamente.

## 4. Modelo de datos

**Cero tablas nuevas** — mismo veredicto que `DASHBOARD` §4. Las entidades que estos widgets
muestran (`Invoice`, `Announcement`) ya son dueñas de `COBRANZA`/`COMUNICACIONES` respectivamente;
duplicar su verdad aquí rompería "un dato, un dueño" (`_system/01_PRINCIPLES.md` §1).

| Entidad consumida | Feature dueña | Endpoint |
|---|---|---|
| `Invoice` | COBRANZA | `GET /me/invoices` |
| `Announcement` | COMUNICACIONES | `GET /condominiums/{id}/announcements` |
| `Contact`, `Property` | DIRECTORIO | `GET /me/contact`, `GET /contacts/{id}/properties` (solo para resolver el condominio propio, ver R-PORTAL-02) |

## 5. Reglas de negocio globales

- **R-PORTAL-01 — Cero agregación server-side:** ningún endpoint nuevo combina datos de `COBRANZA`
  y `COMUNICACIONES` — cada widget hace su propia llamada independiente (mismo principio que
  `DASHBOARD` R-DASH-01/consenso del council: "composición client-side, cada request ya tiene su
  propia autorización").
- **R-PORTAL-02 — Resolución del condominio propio (hallazgo de esta sesión):** a diferencia de un
  admin (que elige su condominio activo haciendo clic en el widget "Mis Condominios",
  `DASHBOARD-B02`), un residente no elige nada — su condominio se deriva de su propia ocupación. Esta
  feature resuelve `activeCondominiumId` para el rol residente encadenando `GET /me/contact` (obtener
  `contact_id`) → `GET /contacts/{contact_id}/properties` (obtener sus unidades, cada una con
  `condominium_id`) → set del mismo store `useActiveCondominium` que ya usa `DASHBOARD-B02`, en vez
  de esperar un clic. **Punto ciego documentado, no resuelto por invención:** un residente con
  ocupaciones en más de un condominio (multi-unidad, caso borde no cubierto por el research del MVP)
  ve solo el primero devuelto — no hay selector todavía. Se resuelve si se vuelve un caso real, no
  antes.
- **R-PORTAL-03 — Sin permiso dedicado:** ambos widgets son visibles a cualquier usuario autenticado
  con al menos una ocupación activa (`property_occupants`) — no introducen un permiso RBAC nuevo,
  mismo criterio que el widget "Accesos Directos" de `DASHBOARD` (`requiredPermission: ninguno`). Un
  admin/staff sin ocupaciones propias simplemente no ve estos widgets (la resolución de R-PORTAL-02
  no encuentra unidades).
- **R-PORTAL-04 — Nunca ocultar un widget fallido:** mismo consenso del Design Council de
  `DASHBOARD` (§8.2, "regla de oro") — si `GET /me/invoices` o el paso de resolución de condominio
  fallan, el widget muestra error + reintentar, nunca desaparece silenciosamente.
- **R-PORTAL-05 — Habeas Data / datos financieros:** "Mi saldo" solo muestra el saldo del propio
  residente (ya garantizado por R-COB-03 de `COBRANZA` del lado servidor) — el widget no agrega
  ninguna exposición nueva, solo consume un endpoint ya scopeado.

## 6. Mapeo de acciones a endpoints (alto nivel)

Cero endpoints nuevos — tabla de referencia a los ya existentes:

| Acción del usuario | Verbo | Endpoint | Feature dueña |
|---|---|---|---|
| Ver resumen de mi saldo | GET | `/me/invoices` | COBRANZA |
| Ver mi contacto (para resolver condominio) | GET | `/me/contact` | DIRECTORIO |
| Ver mis unidades (para resolver condominio) | GET | `/contacts/{id}/properties` | DIRECTORIO |
| Ver avisos de mi condominio | GET | `/condominiums/{id}/announcements` | COMUNICACIONES |

## 7. UI/UX

**Tier:** Estándar — dos widgets de resumen dentro de una pantalla ya existente, sin layout nuevo.

### 7.1 Pantallas afectadas

| Pantalla | Tipo | Ruta | Nueva/Existente |
|---|---|---|---|
| Dashboard (post-login) | Página | `/` (o la que use `DASHBOARD-B01`) | Existente — solo se agregan 2 widgets nuevos al registry, la pantalla en sí no cambia |

### 7.2 Componentes de librería usados

`Card` (contenedor de cada widget, mismo patrón que los widgets de `DASHBOARD`), `Badge` (marcar
"Fijado" en avisos, reutilizado de `COMUNICACIONES`), `Skeleton` (loading), reutiliza
`WidgetDefinition`/`registerWidget` de `DASHBOARD` — sin componentes custom nuevos.

### 7.3 Estados de la vista

| Widget | Loading | Vacío | Error | Normal |
|---|---|---|---|---|
| Mi saldo | Skeleton | "Estás al día — sin saldo pendiente" | Alert destructive + "Reintentar" | Total pendiente + próximo vencimiento + hasta 3 facturas |
| Mis avisos | Skeleton | "Sin avisos por ahora" | Alert destructive + "Reintentar" | Hasta 5 avisos (fijados primero), link "Ver todos" → `/comunicaciones/anuncios` (ya accesible al residente, lectura abierta R-COM-02) |

### 7.4 Navegación

Sin sidebar nuevo — ambos widgets viven en la pantalla de Dashboard existente. "Mis avisos" enlaza a
la pantalla ya existente de `COMUNICACIONES`. "Mi saldo" no enlaza a ninguna pantalla de detalle
(no existe todavía, ver §3 "explícitamente fuera de esta feature").

## 8. Plan de bloques

Una vez `estado_diseño: approved`, el detalle de bloques vive en `BLOCKS.md` (mismo directorio que
este panorama).

## 9. Checklist de aprobación (gate)

- [x] §4 (modelo de datos): **N/A — cero tablas nuevas**, entidades consumidas declaradas con su
      feature dueña.
- [x] §6 (mapeo de acciones a endpoints) cubre toda acción visible al usuario descrita en §1/§5 —
      los 4 endpoints son de otras features, ninguno nuevo.
- [x] §7 (UI/UX) completa: pantallas, componentes y estados declarados (Tier Estándar).
- [x] Nombres de campos y entidades consistentes con `shared/GLOSSARY.md` — sin términos nuevos
      (reutiliza "Invoice"/"Anuncio" ya definidos).
- [x] No hay una feature existente en `features/` que ya cubra esto (revisada `_state/BOARD.md`) —
      confirmado.

> Aprobado por el usuario vía revisión directa del panorama en la misma conversación en que se
> redactó este documento (2026-07-11) — el gate humano de `_system/03_LIFECYCLE.md` §3.

## 10. Análisis de diseño (Claude Code, no un Design Council multi-agente)

Panorama redactado por Claude Code en modo asesoría directa — feature clasificada como "Simple"
(cero tablas, cero endpoints nuevos, dos widgets de composición sobre infraestructura ya construida),
sin correr el protocolo de 3 fases. El hallazgo central de esta sesión (§5, R-PORTAL-02: cómo
resuelve un residente su propio condominio sin el flujo manual de selección que usa un admin) surgió
de contrastar el diseño ya aprobado de `DASHBOARD` (`useActiveCondominium`, poblado por clic) contra
lo que un residente necesita (poblado automáticamente, sin clic) — mismo tipo de verificación que
encontró el bug de `contacts` en `DIRECTORIO` y el rol `vigilante` inexistente en `PORTERIA`. Dos
decisiones de alcance se confirmaron con el usuario antes de redactar: reutilizar el Dashboard
existente en vez de un portal/ruta separado, y limitar "Mi saldo" a un resumen sin pantalla de
detalle.
