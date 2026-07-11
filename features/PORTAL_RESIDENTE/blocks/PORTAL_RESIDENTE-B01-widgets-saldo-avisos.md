---
tipo: bloque
proyecto: web
feature: PORTAL_RESIDENTE
id: PORTAL_RESIDENTE-B01
proyectos: [web]
estado: backlog
depende_de: [DASHBOARD-B02, COBRANZA-B04, COMUNICACIONES-B01, DIRECTORIO-B03]
contrato: consume
verificacion_critica: true
actualizado: 2026-07-11
---

# PORTAL_RESIDENTE-B01 — Widgets "Mi saldo" y "Mis avisos"

## Objetivo

Integrar `PORTAL_RESIDENTE` al Dashboard existente creando el side-effect module
`portal-residente/dashboard.ts` que registra 2 widgets nuevos ("Mi saldo", "Mis avisos") vía el
Widget Registry ya construido en `DASHBOARD-B01`/`DASHBOARD-B02` — mismo patrón zero-touch que usó
`propiedades/dashboard.ts`. Incluye la resolución automática del condominio propio del residente
(R-PORTAL-02 de PANORAMA), necesaria para el widget de avisos.

Único bloque del feature (ver [[../BLOCKS]]).

## Alcance

- **Incluye:**
  - `src/features/portal-residente/dashboard.ts` — side-effect module: registra los 2 widgets
    (`registerWidget()`). Una sola línea de import en `bootstrap.ts`.
  - `src/features/portal-residente/widgets/MiSaldoWidget.tsx` — TanStack Query contra
    `GET /api/v1/me/invoices`. Calcula en el cliente: total pendiente (suma de `saldo` de facturas
    no pagadas), próximo vencimiento (mínima `fecha_vencimiento` entre las pendientes), lista corta
    (hasta 3 facturas más urgentes). Sin permiso requerido (`requiredPermission: undefined`).
  - `src/features/portal-residente/widgets/MisAvisosWidget.tsx` — TanStack Query contra
    `GET /api/v1/condominiums/{activeCondominiumId}/announcements`, `enabled:
    !!activeCondominiumId`. Lista hasta 5 avisos (fijados primero, mismo orden que expone el
    endpoint). Footer: link "Ver todos" → `/comunicaciones/anuncios`.
  - **Resolución automática de `activeCondominiumId` para residentes** (R-PORTAL-02): al montar,
    si el usuario no tiene `activeCondominiumId` seteado y no tiene el rol de quien lo elige
    manualmente (sin permiso `condominiums.ver`, es decir no ve el widget "Mis Condominios" de
    `PROPIEDADES`), dispara `GET /me/contact` → toma `contact_id` → `GET
    /contacts/{contact_id}/properties` → toma el `condominium_id` de la primera unidad devuelta →
    `useActiveCondominium.setActiveCondominium(id)`. Este flujo vive en un hook
    `useResolveResidentCondominium()` en `src/features/portal-residente/hooks/`, invocado una vez
    desde `MisAvisosWidget` (o desde un punto de montaje compartido si aparece un segundo
    consumidor en el futuro).
  - Agregar `import '@/features/portal-residente/dashboard'` en `src/app/bootstrap.ts`.

- **No incluye (explícitamente fuera de este bloque):**
  - Cualquier pantalla de detalle de factura o descarga de paz y salvo (fuera de la feature, ver
    PANORAMA §3).
  - Selector de condominio para residentes multi-unidad (punto ciego documentado, R-PORTAL-02 —
    se toma el primero devuelto, sin selector).
  - Modificaciones a los endpoints consumidos (`/me/invoices`, `/condominiums/{id}/announcements`,
    `/me/contact`, `/contacts/{id}/properties` son contratos ya congelados por sus features
    dueñas — este bloque solo los consume).
  - Sidebar items nuevos — ninguno de los dos widgets necesita entrada de sidebar propia (viven
    solo en el Dashboard).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Residente con 2 facturas pendientes (`saldo` > 0) | Cargar dashboard | Widget "Mi saldo" muestra total pendiente correcto (suma), próximo vencimiento correcto (mínimo), lista de hasta 3 facturas |
| 2 | Residente sin facturas pendientes | Cargar dashboard | "Mi saldo" en estado "Estás al día — sin saldo pendiente" |
| 3 | `GET /me/invoices` retorna 500 | Cargar dashboard | "Mi saldo" muestra Alert destructive + "Reintentar" — no desaparece (R-PORTAL-04) |
| 4 | Residente sin `activeCondominiumId` seteado, sin permiso `condominiums.ver` | Cargar dashboard | Se dispara automáticamente `GET /me/contact` → `GET /contacts/{id}/properties`, `activeCondominiumId` queda seteado sin interacción del usuario |
| 5 | `activeCondominiumId` resuelto | Cargar "Mis avisos" | `GET /condominiums/{id}/announcements` se dispara, muestra hasta 5 avisos, fijados primero |
| 6 | Condominio sin avisos | Cargar "Mis avisos" | Estado "Sin avisos por ahora" |
| 7 | Admin logueado (con permiso `condominiums.ver`, ya tiene su propio flujo de selección manual vía "Mis Condominios") | Cargar dashboard | Los widgets de `PORTAL_RESIDENTE` no disparan la resolución automática de condominio — no interfieren con el flujo manual existente de `PROPIEDADES` |
| 8 | **(Seguridad — R-COB-03 ya la garantiza el servidor)** Residente A intenta que su cliente muestre facturas de otro residente manipulando el estado local | Cargar "Mi saldo" | `GET /me/invoices` solo devuelve lo del usuario autenticado — el widget no puede mostrar datos ajenos aunque el estado del cliente se manipule |
| 9 | Residente con ocupaciones en 2 condominios distintos (caso borde, R-PORTAL-02) | Cargar "Mis avisos" | Se resuelve el condominio de la **primera** unidad devuelta por `/contacts/{id}/properties` — comportamiento documentado, no un bug |
| 10 | Click en "Ver todos" del widget de avisos | Click | Navega a `/comunicaciones/anuncios` (pantalla ya existente, lectura abierta) |

## Contrato

Este bloque **consume** tres contratos: el lock de `COBRANZA-B04` (`/me/invoices`), el lock de
`COMUNICACIONES-B01` (`LOCK-COMUNICACIONES-01`) y el lock de `DIRECTORIO-B03` (`/me/contact`,
`/contacts/{id}/properties`). No puede pasar a `ready` sin los tres vigentes.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real recorriendo los 10 criterios de aceptación, contrastada contra
      PANORAMA §7.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      los tres locks consumidos.
- [ ] Componentes usados provienen de la librería base — sin componentes custom nuevos.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los clientes/hooks nuevos.
- [ ] Dado `verificacion_critica: true` (único bloque de la feature): `verify-council` obligatorio
      antes de que el verifier pueda marcar `done`.
- [ ] `_state/CHANGELOG.md` — entrada de cierre de feature agregada.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Si al momento de ejecutar este bloque `DASHBOARD` ya agregó su propio widget "Mi Unidad" (ver
> `DASHBOARD/PANORAMA.md` §6, todavía no construido), confirmar que no hay duplicación de la lógica
> de resolución de condominio propio — idealmente `useResolveResidentCondominium()` se comparte,
> no se reimplementa.
