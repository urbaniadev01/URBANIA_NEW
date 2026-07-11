---
tipo: referencia
proyecto: api
feature: COBRANZA
actualizado: 2026-07-11
---

# Endpoints: COBRANZA

> **Estado de implementación:** `GET/POST /condominiums/{id}/charge-concepts` y
> `GET/PATCH/DELETE /charge-concepts/{id}` están implementados (COBRANZA-B02).
> `GET/POST /condominiums/{id}/billing-periods`, `GET/PATCH /billing-periods/{id}`,
> `POST/GET /billing-periods/{id}/billing-runs`, `GET /billing-runs/{id}` y los dos endpoints de
> `summary` están implementados (COBRANZA-B03).

## GET /api/v1/condominiums/{condominium}/charge-concepts

**Bloque que lo produce:** [[../../features/COBRANZA/blocks/COBRANZA-B02-crud-conceptos-cobro]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-02]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "condominium_id": "uuid",
      "nombre": "Administración",
      "tipo": "administracion",
      "metodo_calculo": "coeficiente",
      "valor_base": 100000,
      "activo": true,
      "created_by": "uuid",
      "updated_by": null,
      "created_at": "2026-07-11T00:00:00.000000Z",
      "updated_at": "2026-07-11T00:00:00.000000Z"
    }
  ]
}
```

### Comportamiento

- Lista los conceptos de cobro del condominio (R-COB-01), ordenados por `nombre`.
- Requiere permiso `cobranza.conceptos.ver` con scope `organization` u `condominium` cubriendo el
  condominio del path (R-COB-02) — scope `tower`/`unit` nunca basta para datos financieros.
- Conceptos desactivados (`DELETE`d) no aparecen en el listado por defecto.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `PERMISSION_DENIED` | Usuario sin `cobranza.conceptos.ver`, o con el permiso pero scope que no cubre este condominio |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o no pertenece a la organización del usuario |

## POST /api/v1/condominiums/{condominium}/charge-concepts

**Bloque que lo produce:** [[../../features/COBRANZA/blocks/COBRANZA-B02-crud-conceptos-cobro]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-02]]

### Request

```json
{
  "nombre": "string — obligatorio — nombre del concepto (max 255)",
  "tipo": "string — obligatorio — administracion | fondo_imprevistos | multa | extraordinaria",
  "metodo_calculo": "string — obligatorio — coeficiente | fijo | por_area | manual",
  "valor_base": "number — obligatorio — >= 0"
}
```

### Response — éxito (`201`)

```json
{
  "data": {
    "id": "uuid",
    "condominium_id": "uuid",
    "nombre": "Administración",
    "tipo": "administracion",
    "metodo_calculo": "coeficiente",
    "valor_base": 100000,
    "activo": true,
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-11T00:00:00.000000Z",
    "updated_at": "2026-07-11T00:00:00.000000Z"
  }
}
```

### Response — éxito con warning (`201`, cuando `tipo = fondo_imprevistos`)

```json
{
  "data": { "...": "..." },
  "warnings": [
    {
      "code": "FONDO_IMPREVISTOS_VALIDACION_PENDIENTE",
      "detail": {
        "message": "La validación del mínimo legal (1%) para fondo de imprevistos no está implementada en esta fase."
      }
    }
  ]
}
```

### Comportamiento

- `condominium_id` se asigna desde el path — no se acepta en el body.
- `created_by` se asigna con el `user_id` del actor autenticado (R-11).
- Requiere permiso `cobranza.conceptos.gestionar` (segregado de `.ver`, R-COB-13-like).
- R-COB-18: si `tipo = fondo_imprevistos`, la respuesta incluye `warnings[]` no bloqueante — la
  validación real del mínimo legal (1%) queda diferida a una fase futura.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `PERMISSION_DENIED` | Usuario sin `cobranza.conceptos.gestionar` (incluye el caso de tener solo `.ver`), o scope que no cubre este condominio |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o no pertenece a la organización del usuario |
| 409 | `CHARGE_CONCEPT_NAME_DUPLICATE` | Ya existe un concepto con ese nombre (case-insensitive) en el mismo condominio |
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos — incluye `tipo`/`metodo_calculo` fuera del set cerrado |

> **Nota de desviación:** el criterio de aceptación original de `COBRANZA-B02` pedía `422` para
> nombre duplicado; se implementó `409` por consistencia con el resto del API (`PROPERTY_TYPE_NAME_
> DUPLICATE`, `CONDOMINIUM_NAME_DUPLICATE`, etc.) — `409` para conflictos de unicidad de un recurso ya
> existente, `422` reservado para formato/campos faltantes de la request. Ver evidencia de la tarjeta.

## GET /api/v1/charge-concepts/{charge_concept}

**Bloque que lo produce:** [[../../features/COBRANZA/blocks/COBRANZA-B02-crud-conceptos-cobro]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-02]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

Mismo shape de objeto que `POST` (envuelto en `data`, sin `warnings`).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `PERMISSION_DENIED` | Usuario sin `cobranza.conceptos.ver`, o scope que no cubre el condominio del concepto |
| 404 | `CHARGE_CONCEPT_NOT_FOUND` | El concepto no existe, pertenece a otra organización, o está fuera del scope del usuario |

## PATCH /api/v1/charge-concepts/{charge_concept}

**Bloque que lo produce:** [[../../features/COBRANZA/blocks/COBRANZA-B02-crud-conceptos-cobro]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-02]]

### Request

Todos los campos opcionales (mismas reglas que `POST` cuando se envían):

```json
{
  "nombre": "string — opcional",
  "tipo": "string — opcional — administracion | fondo_imprevistos | multa | extraordinaria",
  "metodo_calculo": "string — opcional — coeficiente | fijo | por_area | manual",
  "valor_base": "number — opcional — >= 0"
}
```

### Response — éxito (`200`)

Mismo shape que `POST` — incluye `warnings[]` si el `tipo` resultante es `fondo_imprevistos`.
`updated_by` se asigna con el `user_id` del actor.

### Errores

Mismos códigos que `POST` (403/404/409/422), sin `CONDOMINIUM_NOT_FOUND` (el condominio no se
resuelve desde el path en este endpoint).

## DELETE /api/v1/charge-concepts/{charge_concept}

**Bloque que lo produce:** [[../../features/COBRANZA/blocks/COBRANZA-B02-crud-conceptos-cobro]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-02]]

### Request

Sin body.

### Response — éxito (`204`)

Sin body. El concepto queda `activo = false` y `deleted_at` poblado (soft delete) — no aparece en el
listado por defecto ni se puede editar/ver salvo consulta directa con `withTrashed()` (uso interno).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `PERMISSION_DENIED` | Usuario sin `cobranza.conceptos.gestionar`, o scope que no cubre el condominio del concepto |
| 404 | `CHARGE_CONCEPT_NOT_FOUND` | El concepto no existe, pertenece a otra organización, o está fuera del scope del usuario |

---

# Periodos y facturación (COBRANZA-B03)

## GET /api/v1/condominiums/{condominium}/billing-periods

**Bloque que lo produce:** [[../../features/COBRANZA/blocks/COBRANZA-B03-periodos-facturacion]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "condominium_id": "uuid",
      "anio": 2026,
      "mes": 7,
      "estado": "abierto",
      "created_by": "uuid",
      "updated_by": null,
      "created_at": "2026-07-11T00:00:00.000000Z",
      "updated_at": "2026-07-11T00:00:00.000000Z"
    }
  ]
}
```

### Comportamiento

- Ordenado por año y mes descendente (el periodo más reciente primero).
- Permiso: `cobranza.periodos.ver` con scope `organization`/`condominium` (R-COB-02).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `PERMISSION_DENIED` | Sin `cobranza.periodos.ver`, o scope que no cubre este condominio |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o es de otra organización |

## POST /api/v1/condominiums/{condominium}/billing-periods

**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

### Request

```json
{
  "anio": "integer — obligatorio — 2000..2100",
  "mes": "integer — obligatorio — 1..12"
}
```

### Response — éxito (`201`)

El periodo creado (mismo shape que el listado), con `estado: "abierto"` (R-COB-10).

### Comportamiento

- Permiso: `cobranza.facturacion.ejecutar` — abrir un periodo es parte del ciclo de facturación, no
  una acción de solo-lectura.
- `UNIQUE(condominium_id, anio, mes)` — un condominio no puede tener dos periodos para el mismo mes.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `PERMISSION_DENIED` | Sin `cobranza.facturacion.ejecutar` (incluye tener solo `cobranza.periodos.ver`) |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o es de otra organización |
| 409 | `BILLING_PERIOD_DUPLICATE` | Ya existe un periodo para ese año y mes en este condominio |
| 422 | `VALIDATION_ERROR` | `anio`/`mes` faltantes o fuera de rango |

## GET /api/v1/billing-periods/{billing_period}

**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

Devuelve un periodo (`200`, envuelto en `data`). Permiso: `cobranza.periodos.ver`.
Errores: `401`, `404 BILLING_PERIOD_NOT_FOUND` (inexistente, de otra organización, o fuera del scope
del actor — 404 uniforme, anti-enumeración).

## PATCH /api/v1/billing-periods/{billing_period}

**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

### Request

```json
{ "estado": "cerrado" }
```

Única transición admitida por esta vía (`abierto`/`facturado` → `cerrado`). El paso a `facturado` lo
hace la corrida de facturación, no el cliente.

### Response — éxito (`200`)

El periodo actualizado. **Si quedan facturas con saldo pendiente**, la respuesta incluye `warnings[]`
(R-COB-08-bis) — el cierre **se ejecuta igual**, no se bloquea:

```json
{
  "data": { "estado": "cerrado", "...": "..." },
  "warnings": [
    {
      "code": "BILLING_PERIOD_HAS_PENDING_INVOICES",
      "detail": {
        "invoices_pendientes": 3,
        "message": "El periodo se cerró con facturas pendientes o parciales — quedan abiertas para su cobro."
      }
    }
  ]
}
```

> El diseño (R-COB-08-bis) es explícito en que esto **no** es un `409`: un administrador puede
> necesitar cerrar contablemente un mes aunque haya morosos. La Web exige un checkbox de confirmación
> antes de habilitar el cierre cuando llega este warning.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `BILLING_PERIOD_NOT_FOUND` | Inexistente, de otra organización, o fuera del scope (incluye no tener `cobranza.facturacion.ejecutar`) |
| 409 | `BILLING_PERIOD_ALREADY_CLOSED` | El periodo ya estaba cerrado |
| 422 | `VALIDATION_ERROR` | `estado` ausente o distinto de `cerrado` |

## POST /api/v1/billing-periods/{billing_period}/billing-runs

**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

**Operación asíncrona — patrón `202` + polling** (R-COB-22, convención general en
[[../API_CONTRACT]] §4-ter).

### Request

Sin body.

### Response — aceptado (`202`)

```json
{
  "data": {
    "id": "uuid",
    "billing_period_id": "uuid",
    "ejecutado_por": "uuid",
    "fecha": "2026-07-11T18:45:07+00:00",
    "estado": "en_proceso",
    "resumen": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

La respuesta vuelve **de inmediato**: el prorrateo real corre en `RunBillingPeriodJob` (cola). El
cliente debe hacer polling sobre `GET /billing-runs/{id}` hasta que `estado` sea `completado` o
`fallido`.

### Comportamiento del prorrateo (lo que hace el Job)

Por cada unidad **activa** del condominio (R-COB-05):

> **Qué es una "unidad activa" (R-COB-05, interpretación fijada por COBRANZA-B03):** toda unidad **no
> eliminada** (soft-delete). El `property_status` **no exime de facturación**: bajo Ley 675 el
> propietario paga la cuota de administración según su coeficiente aunque la unidad esté vacía, en
> remodelación o "fuera de servicio". El diseño no lo había definido; el `verify-council` lo levantó
> como ambigüedad y se resolvió así explícitamente.

1. Busca su `property_coefficients` **vigente** de tipo `copropiedad` (`vigente_hasta IS NULL`). Si
   no tiene, la unidad **se omite** — no se factura ni se le asigna un coeficiente por defecto — y se
   registra en `resumen.detalle_omitidas` con motivo `"sin coeficiente vigente"` (R-COB-04).
2. Por cada concepto de cobro **activo** del condominio, calcula el valor según `metodo_calculo`:
   - `coeficiente` → `valor_base × coeficiente` (y guarda el coeficiente como `base_calculo`,
     snapshot inmutable, R-COB-06).
   - `fijo` → `valor_base`.
   - `por_area` → `valor_base × area_m2` (omitido si la unidad no tiene `area_m2`).
   - `manual` → **no se aplica** (R-COB-07: se agregan a mano en COBRANZA-B04).
3. Emite una `invoice` (`numero` = `FAC-{anio}{mes}-{correlativo}`) con sus `invoice_items`,
   `valor_total` = suma de los ítems y `saldo` = `valor_total` (aún sin pagos).
4. Al terminar, el periodo pasa a `facturado` y el run a `completado` con su `resumen`.

> **Los coeficientes no se normalizan.** Si la suma de coeficientes del condominio no es 1.0000, cada
> unidad se factura por el suyo y la diferencia queda visible en el total facturado — no se
> redistribuye silenciosamente entre las demás unidades.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `PERMISSION_DENIED` | Sin `cobranza.facturacion.ejecutar` (p. ej. tiene solo `cobranza.periodos.ver`) |
| 404 | `BILLING_PERIOD_NOT_FOUND` | El periodo no existe o es de otra organización |
| 409 | `BILLING_PERIOD_ALREADY_CLOSED` | No se puede facturar un periodo cerrado |
| 409 | `BILLING_RUN_ALREADY_EXISTS` | Ya hay una corrida `en_proceso` o `completado` para este periodo (R-COB-09) |
| 429 | — | Throttle (`10/min`) |

## GET /api/v1/billing-periods/{billing_period}/billing-runs

**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

Listado de corridas del periodo (`200`, `data[]`, más reciente primero). Permiso:
`cobranza.periodos.ver`. Errores: `401`, `403 PERMISSION_DENIED`, `404 BILLING_PERIOD_NOT_FOUND`.

## GET /api/v1/billing-runs/{billing_run}

**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

**Endpoint de polling** del patrón `202` (R-COB-22).

### Response — éxito (`200`)

```json
{
  "data": {
    "id": "uuid",
    "billing_period_id": "uuid",
    "ejecutado_por": "uuid",
    "fecha": "2026-07-11T18:45:07+00:00",
    "estado": "completado",
    "resumen": {
      "unidades_facturadas": 3,
      "unidades_omitidas": 1,
      "detalle_omitidas": [
        { "property_id": "uuid", "motivo": "sin coeficiente vigente" }
      ],
      "conceptos_omitidos": [
        {
          "property_id": "uuid",
          "charge_concept_id": "uuid",
          "motivo": "la unidad no tiene área registrada"
        }
      ]
    },
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Cuando `estado = fallido`, `resumen` lleva los contadores en cero y un `error` **sin detalles
internos** (nunca el mensaje crudo de la excepción, que podría incluir SQL con valores):

```json
{
  "data": {
    "estado": "fallido",
    "resumen": {
      "unidades_facturadas": 0,
      "unidades_omitidas": 0,
      "detalle_omitidas": [],
      "conceptos_omitidos": [],
      "error": { "code": "BILLING_RUN_FAILED", "trace_id": "uuid" }
    }
  }
}
```

### Comportamiento

- `estado`: `en_proceso` → `completado` | `fallido`.
- **`fallido` garantiza que no se escribió ninguna factura.** Toda la corrida (prorrateo + transición
  de estado) vive en una sola transacción, así que un fallo revierte todo. El operador puede
  redisparar una corrida nueva sin riesgo de duplicar.
- `resumen` es `null` mientras `estado = en_proceso` (el Job todavía lo está armando) y se puebla al
  terminar. Es lo que permite distinguir "completado con todas las unidades" de "completado, pero N
  unidades omitidas y por qué" — sin él, el conteo final sería inexplicable para el usuario.
- **`detalle_omitidas`** — unidades que **no** recibieron factura. `motivo` es uno de:
  - `"sin coeficiente vigente"` — la unidad no tiene un `property_coefficients` de tipo `copropiedad`
    con `vigente_hasta IS NULL` (R-COB-04: sin coeficiente no hay prorrateo posible).
  - `"sin conceptos de cobro aplicables"` — ningún concepto activo del condominio le aplica.
- **`conceptos_omitidos`** — unidades que **sí** recibieron factura, pero a las que un concepto
  puntual no se les aplicó (por lo tanto pagan menos que sus vecinas). `motivo`:
  - `"la unidad no tiene área registrada"` — concepto `por_area` sobre una unidad sin `area_m2`.
  - `"el concepto no aplica a esta unidad"` — resto de casos.

  > Sin este campo, una sub-facturación así sería **indistinguible por query** de una unidad a la que
  > el concepto legítimamente no aplica: la factura sale bien formada, solo con un total menor.
  > Hacerla auditable fue un hallazgo del `verify-council`.
- Permiso: `cobranza.periodos.ver`.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `BILLING_RUN_NOT_FOUND` | Inexistente, de otra organización, o fuera del scope del actor |

## GET /api/v1/condominiums/{condominium}/billing-periods/active/summary

**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

**Este es el endpoint que el widget "Cuotas Pendientes" de `DASHBOARD` consume.**

### Response — éxito (`200`)

```json
{
  "data": {
    "billing_period": {
      "id": "uuid",
      "condominium_id": "uuid",
      "anio": 2026,
      "mes": 8,
      "estado": "facturado"
    },
    "totales": {
      "invoices_total": 3,
      "valor_facturado": 2150000,
      "saldo_pendiente": 2150000,
      "valor_recaudado": 0,
      "invoices_pagadas": 0,
      "invoices_vencidas": 0,
      "invoices_parciales": 0,
      "invoices_pendientes": 3
    }
  }
}
```

Si el condominio no tiene ningún periodo abierto/facturado, `billing_period` es `null` y `totales`
viene en cero — no es un `404`.

### Comportamiento

- "Periodo activo" = el más reciente que **no** esté `cerrado`.
- **Permiso: `billing.ver`** — deliberadamente el permiso más laxo del módulo, no
  `cobranza.periodos.ver`. Es el mismo que `DASHBOARD` ya usa para gatear su navegación/widgets;
  exigir un permiso más fuerte acá dejaría el widget de cartera inaccesible para usuarios que sí
  deben verlo.
- Los conteos por estado se derivan en lectura (R-COB-08): `pagada` = `saldo` 0; `vencida` = saldo > 0
  y `fecha_vencimiento` pasada; `parcial` = saldo > 0 pero menor al total; `pendiente` = el resto.
  Ningún estado se almacena.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `PERMISSION_DENIED` | Sin `billing.ver`, o scope que no cubre este condominio |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o es de otra organización |

## GET /api/v1/billing-periods/{billing_period}/summary

**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-COBRANZA-03]]

Mismo shape de respuesta que el summary del periodo activo, pero para un periodo específico
(incluidos los `cerrado`). Permiso: `cobranza.periodos.ver` — no `billing.ver`: este es el panel de
gestión, no el widget del dashboard. Errores: `401`, `404 BILLING_PERIOD_NOT_FOUND`.
