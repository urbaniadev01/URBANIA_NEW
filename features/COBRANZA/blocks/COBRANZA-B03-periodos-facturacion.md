---
tipo: bloque
proyecto: api
feature: COBRANZA
id: COBRANZA-B03
proyectos: [api]
estado: done
depende_de: [COBRANZA-B02]
contrato: null
verificacion_critica: true
actualizado: 2026-07-09
---

# COBRANZA-B03 — Periodos de facturación y corrida de facturación asíncrona

## Objetivo

Exponer el ciclo de vida de `billing_periods` (`abierto → facturado → cerrado`) y la corrida de
facturación (`billing_runs`) que prorratea gastos comunes por coeficiente de copropiedad — el motor
de cálculo financiero del feature y la primera vez que el contrato de API necesita el patrón
"202 + polling" (R-COB-22). `verificacion_critica: true` porque es lógica de cálculo financiero que
genera las `invoices` reales que se cobran a cada unidad.

## Alcance

- **Incluye:**
  - `GET /condominiums/{id}/billing-periods` — listado.
  - `POST /condominiums/{id}/billing-periods` — abrir periodo (`anio`+`mes`, `UNIQUE` por condominio).
  - `GET /billing-periods/{id}` — detalle.
  - `PATCH /billing-periods/{id}` — cerrar periodo (`estado: cerrado`). Si hay facturas
    `pendiente`/`parcial`, responde `200` con `warnings: [{code:
    "BILLING_PERIOD_HAS_PENDING_INVOICES"}]` en vez de `409` (R-COB-08-bis) — reutiliza el mecanismo
    de `warnings[]` fijado en `COBRANZA-B02`.
  - `POST /billing-periods/{id}/billing-runs` — dispara la corrida. Responde `202` de inmediato con
    `billing_runs.estado = en_proceso` (R-COB-22). El prorrateo real corre en un Job encolado
    (`jobs`/`job_batches`, ya disponibles desde `API_BOOTSTRAP`): por cada unidad activa del
    condominio (R-COB-05), lee `property_coefficients` vigentes de tipo `copropiedad` (lectura
    cross-context de `Properties`, ver
    [[../../../shared/adr/ADR-002-lectura-cross-context-modulo-monolito]]), genera `invoices` +
    `invoice_items` con `base_calculo` como snapshot inmutable (R-COB-06), y omite unidades sin
    coeficiente vigente registrando el motivo en `billing_runs.resumen` (decisión 8:
    `{unidades_facturadas, unidades_omitidas, detalle_omitidas: [{property_id, motivo}]}`).
  - `GET /billing-periods/{id}/billing-runs` — listado de corridas del periodo.
  - `GET /billing-runs/{id}` — detalle para polling (incluye `resumen` cuando `estado != en_proceso`).
  - `GET /condominiums/{id}/billing-periods/active/summary` — panel de cartera del periodo activo
    (endpoint que `DASHBOARD` espera consumir eventualmente, ver `BLOCKS.md` "Acción pendiente
    cross-feature").
  - `GET /billing-periods/{id}/summary` — panel de cartera de un periodo específico.
  - Documentar el patrón "202 + polling" como convención general nueva en `api/API_CONTRACT.md`
    (R-COB-22), no como excepción puntual de esta feature.
  - Middleware RBAC: `cobranza.periodos.ver`, `cobranza.facturacion.ejecutar`.

- **No incluye (explícitamente fuera de este bloque):**
  - Listado/detalle de `invoices` individuales fuera del `resumen` agregado — `COBRANZA-B04`.
  - Registro de pagos — `COBRANZA-B05`.
  - `invoices.estado` derivado (`vencida`/`pagada`/etc.) — se define y expone en `COBRANZA-B04`, este
    bloque solo escribe filas de `invoices` con `saldo = valor_total` al crearlas.
  - Reintentar automáticamente un `billing_run` que llegó a `fallido` — el usuario dispara uno nuevo
    manualmente; no hay reintento automático en Fase 1.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `cobranza.facturacion.ejecutar` | `POST .../billing-periods` con `anio=2026, mes=7` | `201`, `estado: abierto` |
| 2 | Periodo ya existe para ese `anio`+`mes` | `POST .../billing-periods` con los mismos valores | `422` — `UNIQUE(condominium_id, anio, mes)` |
| 3 | Periodo `abierto`, condominio con 10 unidades activas (8 con coeficiente vigente, 2 sin) | `POST /billing-periods/{id}/billing-runs` | `202` inmediato, `billing_runs.estado = en_proceso` |
| 4 | `billing_run` del #3 procesado por el Job | `GET /billing-runs/{id}` (polling tras completar) | `estado: completado`, `resumen: {unidades_facturadas: 8, unidades_omitidas: 2, detalle_omitidas: [...]}` |
| 5 | `billing_run` completado del #3 | `Invoice::where('billing_run_id', $id)->count()` | `8` — una por unidad con coeficiente vigente, ninguna para las 2 omitidas |
| 6 | `billing_run` `en_proceso` para un periodo | Disparar `POST .../billing-runs` de nuevo para el mismo periodo | `409` o error de negocio — no se permite un segundo `billing_run` concurrente completado (R-COB-09), verificado también a nivel BD por el `UNIQUE` parcial de `COBRANZA-B01` |
| 7 | Periodo `abierto` con facturas `pendiente` | `PATCH /billing-periods/{id}` con `{estado: cerrado}` | `200` con `warnings: [{code: "BILLING_PERIOD_HAS_PENDING_INVOICES"}]`, periodo pasa a `cerrado` de todas formas (no bloqueante) |
| 8 | Periodo `abierto` con todas las facturas `pagada` | `PATCH /billing-periods/{id}` con `{estado: cerrado}` | `200` sin `warnings[]` |
| 9 | Usuario con `cobranza.periodos.ver` (sin `.ejecutar`) | `POST .../billing-runs` | `403` — segregación ver/ejecutar (la acción de mayor impacto del feature) |
| 10 | Usuario con `billing.ver` únicamente | `GET /condominiums/{id}/billing-periods/active/summary` | `200` — este endpoint usa `billing.ver`, no `cobranza.periodos.ver`, para no bloquear el widget de `DASHBOARD` |
| 11 | Unidad sin `property_coefficients` vigente de tipo `copropiedad` | Corrida de facturación sobre esa unidad | La unidad se omite (no genera `invoice`), aparece en `detalle_omitidas` con motivo `"sin coeficiente vigente"` |

## Contrato

Este bloque **produce** contrato — al llegar a `done`, se crea `LOCK-COBRANZA-03` en
`_state/contracts/CONTRACT_LOCKS.md` para los 8 endpoints de periodos/facturación, consumido por
`COBRANZA-B08`. **Este es también el lock que `DASHBOARD` necesita** para su widget de cartera —
documentar explícitamente el endpoint `GET /condominiums/{id}/billing-periods/active/summary` en el
lock para que quien resuelva la acción pendiente cross-feature (ver `BLOCKS.md`) lo encuentre.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] Verificación funcional real cubriendo los 11 criterios, incluido el ciclo completo `202` →
      polling → `completado` con `resumen` real — request/response reales pegados.
- [x] Verificación de que el Job de facturación corre en cola real (no síncrono simulando 202) —
      salida de `queue:work` pegada, con la profundidad de la cola Redis antes/después.
- [x] `LOCK-COBRANZA-03` creado en `_state/contracts/CONTRACT_LOCKS.md`, incluyendo el endpoint que
      `DASHBOARD` consumirá.
- [x] `api/API_CONTRACT.md` actualizado con los 9 endpoints nuevos (6 códigos de error + 1 warning) y
      la convención general "202 + polling" documentada como patrón reusable (§4-ter).
- [x] `api/endpoints/COBRANZA.md` actualizado con el detalle de estos 9 endpoints.
- [x] Dado que `verificacion_critica: true`, el `verify-council` emitió veredicto — ver sección
      "Verificación (verify-council)" abajo. Veredicto inicial: **❌ bloqueante** (1 crítico, unánime
      en las 3 lentes). Todos los hallazgos bloqueantes fueron corregidos y re-verificados.

## Evidencia

### Implementación

- **`BillingPeriodController`** — `index`/`store`/`show`/`update`. El cierre (`PATCH`) implementa
  R-COB-08-bis: consulta facturas con `saldo > 0` (el `estado` de factura es derivado, R-COB-08, así
  que se consulta el hecho subyacente) y devuelve `200` + `warnings[]` en vez de `409`.
- **`BillingRunController`** — `store` (→ `202`), `index`, `show` (polling). Valida permisos, estado
  del periodo y corridas concurrentes **antes** de encolar. R-COB-09 se verifica en aplicación
  (`409 BILLING_RUN_ALREADY_EXISTS`, que además cubre `en_proceso` — el UNIQUE parcial de BD solo
  cubre `completado`).
- **`RunBillingPeriodJob`** (`src/Billing/Application/Jobs/`) — el prorrateo real, en cola. `$tries = 1`
  (sin reintento automático: reintentar a medio camino duplicaría facturas). Todo el prorrateo corre
  dentro de una transacción; un `Throwable` deja el run en `fallido` con el error en `resumen`.
  Lectura cross-context read-only de `Properties` (`EloquentProperty`, `EloquentPropertyCoefficient`)
  per ADR-002, scopeada al mismo `condominium_id` del periodo.
- **`BillingSummaryController`** — `active` (permiso `billing.ver`, el que DASHBOARD usa) y `show`
  (permiso `cobranza.periodos.ver`). Los conteos por estado se derivan en lectura (R-COB-08).
- **`HasBillingPermission`** (trait nuevo) — extraída la resolución de permisos que
  `ChargeConceptController` (B02) ya tenía inline y ahora comparten los 4 controllers de Billing.
- **`Decimal`** (`src/Billing/Application/Support/`) — conversión de atributos numéricos de Eloquent
  (`mixed`) a `float`/`int` con validación en runtime. No es un cast para silenciar al analizador:
  las columnas de dinero/coeficiente son `NOT NULL` numéricas, así que un valor no numérico ahí es un
  bug real que debe fallar ruidosamente en el prorrateo en vez de degradar a 0 y facturar de menos.
- **`CobranzaPermissionsSeeder`** extendido: asigna `billing.ver`, `cobranza.periodos.ver` y
  `cobranza.facturacion.ejecutar` a `admin`/`manager`. `pagos.*` y `cobranza.paz_salvo.*` siguen sin
  asignar a propósito — R-COB-13 exige que no coincidan por defecto en el mismo rol (los asignará
  B05/B06 con la separación que corresponda).
- **Migración `failed_jobs`** — ver hallazgo #1 abajo.

### `composer ci` completo

```
$ composer ci
{"tool":"pint","result":"passed"}
[OK] No errors (PHPStan, 234 archivos)
Tests: 324 passed (1110 assertions)
```

324 = 310 previos (post `COBRANZA-B02`) + 14 nuevos de este bloque. Sin regresiones.

### Tests de feature (14 nuevos, `tests/Feature/Billing/BillingPeriodTest.php`)

```
$ php artisan test tests/Feature/Billing/
PASS  Tests\Feature\Billing\BillingPeriodTest
✓ create billing period returns 201 with estado abierto                          (CA1)
✓ duplicate billing period for the same anio and mes returns 409                 (CA2)
✓ dispatching a billing run returns 202 immediately and queues the job           (CA3)
✓ billing run job prorates, skips units without a coefficient and fills resumen  (CA4, CA5, CA11)
✓ prorating does not silently assume coefficients sum to 1.0                     (nota de la tarjeta)
✓ a second billing run for the same period is rejected                           (CA6)
✓ closing a period with pending invoices returns 200 with a warning              (CA7)
✓ closing a period with all invoices paid returns 200 without warnings           (CA8)
✓ user with only cobranza.periodos.ver cannot dispatch a billing run             (CA9)
✓ active period summary is accessible with only billing.ver                      (CA10)
✓ period summary reflects paid invoices
✓ billing run applies fijo and por_area concepts and skips manual ones           (R-COB-07)
✓ billing period from another organization returns 404
✓ unauthenticated access returns 401

PASS  Tests\Feature\Billing\ChargeConceptTest  (13, sin cambios)

Tests: 27 passed (114 assertions)
```

**Caso exigido por la nota de `verificacion_critica`** (coeficientes que no suman 1.0000):
`prorating does not silently assume coefficients sum to 1.0` — 3 unidades con coeficiente 0.30 cada
una (suma 0.90, no 1.00) sobre un concepto de 1.000.000. Cada unidad se factura por su propio
coeficiente (300.000 c/u, total 900.000): el 10% faltante **no** se redistribuye entre las demás ni
se absorbe silenciosamente — la diferencia queda visible en el total facturado.

### Verificación funcional real — el Job corre en cola Redis, no `sync`

El DoD exige probar que la asincronía es real. Se detuvo el worker, se disparó una corrida y se
comprobó que el job quedaba **pendiente en Redis** con el run en `en_proceso`; recién al levantar el
worker se procesó:

```
=== Job pendiente en la cola Redis ANTES del worker ===
$ docker compose exec redis redis-cli LLEN urbania_database_queues:default
1

=== Polling ANTES de levantar el worker: sigue en_proceso (asincronía real) ===
$ curl .../api/v1/billing-runs/019f5280-4be5-...
{"data":{"estado":"en_proceso","resumen":null,...}}

=== queue:work en primer plano ===
$ docker compose exec php php artisan queue:work --queue=default --tries=1 --stop-when-empty
  2026-07-11 18:46:44 Urbania\Billing\Application\Jobs\RunBillingPeriodJob  RUNNING
  2026-07-11 18:46:45 Urbania\Billing\Application\Jobs\RunBillingPeriodJob  1s DONE

=== Cola Redis DESPUES del worker ===
$ docker compose exec redis redis-cli LLEN urbania_database_queues:default
0
```

### Verificación funcional real — los 11 criterios (curl contra el servidor Docker)

```
=== CA1: POST periodo (201, abierto) ===
HTTP:201 — {"data":{"anio":2026,"mes":7,"estado":"abierto",...}}

=== CA2: POST periodo duplicado (409) ===
HTTP:409 — {"error":{"code":"BILLING_PERIOD_DUPLICATE",...}}

=== CA3: POST billing-run (202 INMEDIATO, en_proceso, resumen null) ===
HTTP:202 — {"data":{"estado":"en_proceso","resumen":null,...}}

=== CA4: GET /billing-runs/{id} — polling → completado + resumen ===
HTTP:200 — {"data":{"estado":"completado","resumen":{
  "unidades_facturadas":3,"unidades_omitidas":1,
  "detalle_omitidas":[{"motivo":"sin coeficiente vigente","property_id":"019f527e-aae0-..."}]}}}

=== CA5 + CA11: facturas generadas (3 unidades con coeficiente; la 4a, sin coeficiente, omitida) ===
Facturas: 3
FAC-202607-00001 | unidad F-101 | total 650000.00 | saldo 650000.00 | items 2
    - Administracion: 600000.00 (base_calculo: 0.3000)   ← 2.000.000 × 0.30
    - Vigilancia:      50000.00 (base_calculo: null)      ← concepto fijo, sin base_calculo
FAC-202607-00002 | unidad F-102 | total 650000.00 | saldo 650000.00 | items 2
FAC-202607-00003 | unidad F-103 | total 850000.00 | saldo 850000.00 | items 2
    - Administracion: 800000.00 (base_calculo: 0.4000)   ← 2.000.000 × 0.40

=== CA6: segunda corrida sobre periodo ya facturado (409) ===
HTTP:409 — {"error":{"code":"BILLING_RUN_ALREADY_EXISTS","message":"Este periodo ya fue facturado..."}}

=== CA7: cerrar periodo CON facturas pendientes → 200 + warnings (NO 409) ===
HTTP:200 — {"data":{"estado":"cerrado",...},"warnings":[{"code":"BILLING_PERIOD_HAS_PENDING_INVOICES",
  "detail":{"invoices_pendientes":3,...}}]}

=== CA8: cerrar periodo SIN facturas pendientes → 200 sin warnings ===
HTTP:200 — {"data":{"estado":"cerrado",...}}   (sin campo `warnings`)

=== CA9: usuario con SOLO cobranza.periodos.ver → POST billing-run (403) ===
HTTP:403 — {"error":{"code":"PERMISSION_DENIED","message":"No tiene permisos para ejecutar la facturación..."}}
=== CA9 control: el mismo usuario SÍ puede ver el periodo ===
HTTP:200

=== CA10: summary del periodo activo con billing.ver ===
HTTP:200 — {"data":{"billing_period":{"anio":2026,"mes":8,"estado":"facturado"},
  "totales":{"invoices_total":3,"valor_facturado":2150000,"saldo_pendiente":2150000,
             "valor_recaudado":0,"invoices_pendientes":3,"invoices_pagadas":0}}}
```

Datos de prueba (condominio, unidades, conceptos, periodos, facturas, usuarios y roles ad-hoc)
limpiados de la BD de desarrollo tras la verificación.

### Hallazgos reales encontrados y corregidos

1. **`failed_jobs` no existía** — el PANORAMA (R-COB-22) asumía que `jobs`/`job_batches`/`failed_jobs`
   "ya están disponibles desde `API_BOOTSTRAP`". Verificado contra la BD real: ninguna de las tres
   existe. Para `jobs`/`job_batches` es correcto y esperado (`QUEUE_CONNECTION=redis` guarda el
   payload en Redis, no necesita tabla), pero `config('queue.failed.driver')` sigue apuntando a
   `database-uuids`, que **sí** requiere `failed_jobs` para registrar fallos permanentes — sin ella,
   un job que agota reintentos falla al intentar loguearse a sí mismo. Migración agregada
   (`2026_07_11_000032_create_failed_jobs_table.php`). Este bloque es el primero del vault que
   despacha un Job encolado, así que el hueco no se podía haber detectado antes.

2. **`BillingMigrationTest` (de `COBRANZA-B01`) se rompió al agregar la migración de `failed_jobs`** —
   exactamente la misma fragilidad que ya había corregido en `DirectorioMigrationTest` durante B01:
   usaba `migrate:rollback --step=8` asumiendo que sus 8 migraciones eran "las últimas 8" del
   directorio plano. La migración nueva las desplazó y el rollback empezó a apuntar a las incorrectas.
   Corregido con `--path` explícito a los 8 archivos, inmune a lo que se agregue después. **Que la
   misma trampa haya reaparecido confirma que no era un caso aislado**: cualquier test de migración
   que use `--step` relativo en este repo va a romperse con la siguiente feature que agregue tablas.

3. **`billing.ver` existía como fila pero ningún rol lo tenía.** `COBRANZA-B01` creó el permiso
   (cerrando el gap de que DASHBOARD nunca lo persistió), pero sin asignarlo a ningún rol el endpoint
   de cartera que DASHBOARD va a consumir habría sido inaccesible para **todo** usuario. Asignado a
   `admin`/`manager` en el seeder. Detectado al escribir el test de CA10, no en producción.

### Nota sobre el criterio de aceptación #2 (409 vs 422)

La tarjeta pedía `422` para periodo duplicado; se implementó `409 BILLING_PERIOD_DUPLICATE`,
siguiendo el criterio que el usuario confirmó explícitamente al cerrar `COBRANZA-B02` (409 para
conflictos de unicidad de un recurso ya existente; 422 reservado a formato/campos faltantes).
Documentado en `LOCK-COBRANZA-03`.

### Archivos creados

- `database/migrations/2026_07_11_000033_add_billing_integrity_constraints.php` (post-council:
  `UNIQUE(billing_period_id, property_id)` + índices sobre `billing_period_id`)
- `database/migrations/2026_07_11_000032_create_failed_jobs_table.php`
- `src/Billing/Application/Jobs/RunBillingPeriodJob.php`
- `src/Billing/Application/Support/Decimal.php`
- `src/Billing/Infrastructure/Http/Concerns/HasBillingPermission.php`
- `src/Billing/Infrastructure/Http/Controllers/BillingPeriodController.php`
- `src/Billing/Infrastructure/Http/Controllers/BillingRunController.php`
- `src/Billing/Infrastructure/Http/Controllers/BillingSummaryController.php`
- `src/Billing/Infrastructure/Http/Requests/BillingPeriod/StoreBillingPeriodRequest.php`
- `src/Billing/Infrastructure/Http/Requests/BillingPeriod/UpdateBillingPeriodRequest.php`
- `src/Billing/Infrastructure/Http/Resources/BillingPeriodResource.php`
- `src/Billing/Infrastructure/Http/Resources/BillingRunResource.php`
- `tests/Feature/Billing/BillingPeriodTest.php`

### Archivos modificados

- `routes/api.php` — 9 endpoints nuevos.
- `database/seeders/CobranzaPermissionsSeeder.php` — asignación de `billing.ver`,
  `cobranza.periodos.ver`, `cobranza.facturacion.ejecutar` a `admin`/`manager`.
- `src/Billing/Infrastructure/Http/Controllers/ChargeConceptController.php` — usa el trait
  `HasBillingPermission` en vez de su copia inline (sin cambio de comportamiento; los 13 tests de B02
  siguen pasando sin modificarse).
- `tests/Unit/Billing/BillingMigrationTest.php` — fix de `--step=8` → `--path` (hallazgo #2).
- `_state/contracts/CONTRACT_LOCKS.md` — `LOCK-COBRANZA-03` creado.
- `api/API_CONTRACT.md` — §4-ter (patrón 202 + polling) + 6 códigos de error + 1 warning.
- `api/endpoints/COBRANZA.md` — detalle de los 9 endpoints.
- `_state/BOARD.md` — estado del bloque.

## Verificación (verify-council)

**Protocolo:** 3 fases (`_system/06_AGENT_ROLES.md` §12) — divergencia con `sec-reviewer`,
`perf-reviewer` y `code-reviewer` en paralelo; peer review anonimizado (hallazgos A/B/C); síntesis.

**Veredicto inicial: ❌ BLOQUEANTE — unánime en las 3 lentes.**

### El hallazgo crítico: doble facturación de un condominio entero, reportada como `fallido`

Los tres revisores convergieron **de forma independiente** en el mismo defecto, cada uno llegando por
un camino distinto. La causa raíz: **`billing_runs.estado` se usaba como guard de no-duplicación pero
se escribía FUERA de la transacción que commitea las facturas.** El `UNIQUE` parcial que
`COBRANZA-B01` había creado exactamente para esto (`billing_runs_completado_unique`) disparaba
*después* del commit, así que no podía revertir nada.

El peer review destapó que no era un escenario de laboratorio: tiene **tres rutas independientes**,
y ninguna estaba cubierta por los 324 tests en verde ni por la evidencia funcional con curl.

1. **Concurrencia** — dos `POST` simultáneos (doble clic, retry del cliente) pasaban ambos el
   check-then-act sin lock del controller. El `UNIQUE` de BD solo cubría `completado`, no
   `en_proceso`.
2. **Fallo tras el commit, SIN concurrencia** — cualquier caída entre el commit de las facturas y el
   `save()` del estado dejaba el run en `fallido` **con las facturas ya escritas**. Y como un run
   `fallido` no bloquea uno nuevo, el operador veía "fallido", redisparaba, y duplicaba. El camino
   feliz-con-fallo bastaba.
3. **Redelivery del job** (detectada recién en el peer review) — si el worker muere tras el commit, la
   cola reentrega el job; el guard veía el run todavía en `en_proceso` y **volvía a prorratear
   entero**. Un solo usuario, cero concurrencia. Probablemente la ruta más común en producción.

Y el mecanismo que lo **enmascaraba**: `siguienteNumeroFactura()` contaba las facturas del condominio
*globalmente*, así que el segundo prorrateo numeraba `00301..00600` en vez de repetir `00001..00300`
— no colisionaba con `UNIQUE(condominium_id, numero)` y pasaba inadvertido. El `resumen` del run
duplicado decía `unidades_facturadas: 0` mientras cada unidad quedaba con el saldo duplicado.

> **Autocrítica del `perf-reviewer` en el peer review** (vale citarla porque explica el sesgo): *"Tenía
> la causa raíz en la mano y no la nombré. Salté a proponer un índice nuevo en vez de ver que mover
> tres líneas adentro de la transacción era el fix, y que B01 ya había construido la defensa correcta
> que este código dejó fuera de alcance."*

### Fixes aplicados

| # | Hallazgo | Fix |
|---|---|---|
| **CRÍTICO** | Doble facturación (3 rutas) | (a) **`UNIQUE(billing_period_id, property_id) WHERE deleted_at IS NULL` en `invoices`** — la invariante de negocio real ("una unidad, una factura por periodo"), que cierra las tres rutas sin importar por cuál se llegue y protege también a escritores futuros (`COBRANZA-B04`, backfills). (b) **Prorrateo + transición de estado + `resumen` en una sola transacción**, así `fallido` vuelve a significar "no se escribió nada". (c) **Re-chequeo bajo `lockForUpdate`** al entrar al Job. (d) **Dispatch atómico** en el controller (`lockForUpdate` sobre el periodo). |
| **ALTO** | Job muerto → periodo bloqueado **para siempre** (el controller rechaza toda corrida nueva mientras haya una `en_proceso`, y sin `failed()` el `catch` nunca corre) | Hook **`failed()`** que marca el run como `fallido`, + `$timeout = 60` (menor que el `retry_after` de 90s de la cola, para que un job lento muera antes de que la cola lo redelivere y se solapen dos prorrateos). |
| **ALTO** | **N+1 del Job era la mecha del bloqueo anterior**: ~2.106 queries con 300 unidades × 5 conceptos. A ~2.000 unidades el runtime cruzaba el `--timeout` de 60s del worker → el proceso moría → periodo bloqueado. *El condominio más grande era exactamente el que se rompía, y se rompía por ser grande.* | `whereIn` de coeficientes (1 query en vez de N) + **bulk insert** de facturas e ítems. ~2.106 → ~8 queries. |
| **ALTO** | Seq Scan en el endpoint que consume DASHBOARD: el único índice era el compuesto `(condominium_id, billing_period_id)` y Postgres no puede usarlo sin su columna líder, pero las queries reales filtran **solo** por `billing_period_id`. *El código era fiel al `PANORAMA.md` §4; el panorama no coincidía con las queries reales.* | Índices sobre `invoices.billing_period_id` y `billing_runs.billing_period_id`. |
| **ALTO** | **Sub-facturación silenciosa**: un concepto `por_area` sobre una unidad sin `area_m2` se omitía sin dejar rastro — factura bien formada, total menor, **indistinguible por query** de una unidad a la que el concepto legítimamente no aplica. Pérdida de ingreso no auditable. | `resumen.conceptos_omitidos[]` con `{property_id, charge_concept_id, motivo}`. Documentado en el lock y en `api/endpoints/COBRANZA.md`. |
| **ALTO** | **R-COB-05 sin implementar ni definir**: "solo unidades activas", pero el diseño nunca dijo qué es "activa". | Ambigüedad elevada al usuario. Decisión: **"activa" = no eliminada**; el `property_status` no exime de facturación (Ley 675 — el propietario paga aunque la unidad esté fuera de servicio). Documentado explícitamente en el lock, los endpoints y el código. |
| **MEDIO** | `resumen.error` persistía y devolvía por API el `$e->getMessage()` crudo — un `QueryException` de PDO incluye el SQL **con los bindings** (valores monetarios reales). | `{code: "BILLING_RUN_FAILED", trace_id}`; el detalle solo a `report()`. |
| **MEDIO** | Numeración: correlativo global del condominio (el mecanismo que enmascaraba la duplicación). | Correlativo **por periodo** — además hace que `UNIQUE(condominium_id, numero)` actúe como segunda red. |
| **MEDIO** | `totales()` hidrataba todas las facturas del periodo en PHP para agregarlas. | Agregación SQL (`COUNT(*) FILTER`). |

### Tests de regresión (9 nuevos)

El `perf-reviewer` señaló algo estructural: **`QUEUE_CONNECTION=sync` colapsa la ventana temporal en
la que vive el bug, así que ningún test que use `sync` puede encontrarlo jamás** — no era un test
faltante, era una clase entera de bugs que el suite era ciego a ver. Los tests nuevos atacan el Job
directamente, sin pasar por el driver de cola:

```
✓ re-running the job for an already-billed period does not duplicate invoices   (ruta 3: redelivery)
✓ a second run cannot bill a period already completed by another run            (ruta 1: concurrencia)
✓ a failed run leaves no invoices behind                                        (ruta 2: fallo post-commit)
✓ failed() hook marks a stuck run as fallido so the period is not blocked forever
✓ a concept skipped for a unit is recorded in resumen.conceptos_omitidos
✓ a completed billing run leaves the period in estado facturado                 (R-COB-10)
✓ prorating rounds each item to cents and valor_total equals the sum of its items
✓ closing an already closed period returns 409
✓ a billing run cannot be dispatched for a closed period
```

**Prueba de mutación** — un test que pasa no prueba nada si también pasaba contra el código
vulnerable. Se revirtieron temporalmente las defensas para confirmar que los tests **fallan** contra
el bug real:

```
# Sin el UNIQUE de BD ni el re-chequeo de aplicación:
⨯ re-running the job for an already-billed period does not duplicate invoices
  Failed asserting that 4 is identical to 2.      ← el condominio facturado DOS VECES

# Con solo la defensa de BD (re-chequeo de aplicación desactivado): PASA
#   → confirma que `invoices_period_property_unique` atrapa la duplicación por sí solo.
```

### Re-verificación completa

```
$ composer ci
{"tool":"pint","result":"passed"}
[OK] No errors (PHPStan, 235 archivos)
Tests: 333 passed (1144 assertions)
```

333 = 324 previos + 9 de regresión del council. Sin regresiones.

**Verificación funcional real de la race, contra la cola Redis** (dos `POST` disparados en paralelo
con `&` + `wait`):

```
=== DOS POST SIMULTANEOS (la race del council) ===
B:HTTP:409   {"error":{"code":"BILLING_RUN_ALREADY_EXISTS","message":"Ya hay una corrida en proceso..."}}
A:HTTP:202   {"data":{"estado":"en_proceso",...}}

=== worker procesa la cola ===
  RunBillingPeriodJob  RUNNING
  RunBillingPeriodJob  3s DONE

=== Resultado ===
Corridas: 1
  - completado | facturadas: 2
Facturas: 2 (esperado: 2, una por unidad)
  - FAC-202610-00001 | total 500000.00
  - FAC-202610-00002 | total 500000.00
```

Antes del fix, ambos POST devolvían `202` y encolaban dos corridas.

### Hallazgos NO bloqueantes, deliberadamente diferidos

- **Vigencia del coeficiente ignora `vigente_desde` y la fecha del periodo** (BAJO, 2 lentes): facturar
  un periodo pasado usa el coeficiente *actual*, no el vigente en ese periodo; y un coeficiente con
  `vigente_desde` futuro se usaría hoy. `base_calculo` deja el snapshot, así que es auditable a
  posteriori. **No se corrige acá**: cambia la semántica de selección de coeficientes y merece su
  propia decisión de diseño (afecta también a `COBRANZA-B04`). Queda anotado como deuda explícita.
- **`fecha_vencimiento = now()->addDays(15)`** — número mágico desacoplado del periodo, no está en el
  contrato. Diferido: configurarlo es alcance de otro bloque.
- **Permiso evaluado contra el UUID crudo de la URL** en vez del id resuelto (BAJO, fail-closed): un
  UUID en mayúsculas pasaría el tenant check y fallaría el de permisos → 403 espurio. Frágil pero no
  explotable.

### Lo que el council confirmó como correcto

- **Sin hallazgos de authZ/tenant-isolation explotables.** `HasBillingPermission` solo acepta scope
  `organization`/`condominium` (nunca `tower`/`unit`, R-COB-02); el Job preserva el scope de tenant
  (ADR-002); un usuario no puede pollear ni facturar el condominio de otra organización; el payload
  del job en Redis/`failed_jobs` es solo un UUID (sin PII ni datos financieros).
- **El redondeo es correcto**: `round()` por ítem, y `valor_total` = suma exacta de los ítems ya
  redondeados. Redondear al final sería *peor* (el total no cuadraría con las líneas de la factura).
- **El prorrateo no normaliza coeficientes** (el caso que la nota de `verificacion_critica` exigía):
  si suman 0.90, cada unidad paga lo suyo y el faltante no se redistribuye en silencio.
- **La transacción única es la decisión correcta** — trocearla daría facturación parcial ante un fallo.

## Notas

> Este bloque es el más sensible de la cadena API: si el prorrateo tiene un error de redondeo o
> selección de unidades, todas las facturas de un periodo quedan mal desde el origen. El
> `verify-council` debe incluir al menos un caso con coeficientes que no sumen exactamente 1.0000
> (mismo espíritu que `COEFFICIENT_SUM_MISMATCH` de `PROPIEDADES`) para confirmar que el prorrateo no
> asume silenciosamente que la suma es perfecta. — **Cumplido**: ver el test
> `prorating does not silently assume coefficients sum to 1.0` y la sección de verificación arriba.

> **El `verify-council` justificó su costo.** Los 324 tests en verde, el `composer ci` limpio y la
> verificación funcional con curl contra la cola real **no detectaron** un defecto que facturaba dos
> veces a todo un condominio y lo reportaba como `fallido`. La evidencia de la implementación no era
> falsa — era insuficiente, y de una forma que solo se ve con otras lentes encima. Es exactamente el
> escenario para el que `verificacion_critica: true` existe.
