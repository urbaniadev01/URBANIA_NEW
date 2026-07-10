---
tipo: feature
proyecto: shared
feature: COBRANZA
estado_diseño: approved
design_council: completado
actualizado: 2026-07-09
---

> **Design Council completado (2026-07-09).** Este documento es el output consolidado del
> protocolo de 3 fases (divergencia con `design-architect`, `design-ux`, `design-security`; peer
> review anonimizada; síntesis) — ver §9 para el veredicto completo. Los 10 puntos abiertos del
> borrador original (ex-§9) están resueltos o explícitamente diferidos con justificación.
>
> Mientras `estado_diseño` no cambie a `approved` por el gate humano de `_system/03_LIFECYCLE.md`
> §3, ningún agente crea `BLOCKS.md` ni tarjetas de bloque para esta feature.

# Feature: COBRANZA

## 1. Resumen y motivación

COBRANZA gestiona el ciclo de facturación de gastos comunes de un condominio: conceptos de cobro
configurables, periodos de facturación, la corrida que prorratea y emite cuentas de cobro por unidad
según su coeficiente de copropiedad, el registro manual de pagos/abonos, y la emisión de paz y salvo.
Es la feature 1.3 del MVP (Fase 1) — la primera con impacto financiero directo, y la razón de ser
comercial de la demo: sin esto no hay nada que mostrarle a un cliente que se parezca a "administrar
un conjunto". Depende de `PROPIEDADES` (coeficientes, unidades) y `DIRECTORIO` (contactos, ocupante
principal) — ambas ya `approved`.

Esta feature se limita **estrictamente** al alcance que define la tabla autoritativa de fases (línea
1.3 de `PLAN_FASES_DESARROLLO.md`, fuera del repo): conceptos de cobro, periodos, facturación por
coeficiente, registrar pago manual, paz y salvo, sobre exactamente 8 tablas nuevas. Todo lo demás que
aparece en el research crudo pero no en esa tabla —interés de mora, acuerdos de pago, pagos online,
reportes de cartera avanzados, contabilidad NIIF— se documenta explícitamente como fuera de alcance en
§3 y §5, resolviendo a favor de la tabla de fases (más reciente/autoritativa) una inconsistencia real
detectada frente al research detallado, que sí incluye `late_interest_config`/`payment_agreements` en
el mismo diagrama ER de Cobranza.

Es también la **primera feature del vault que necesita leer datos de otro bounded context ya
implementado en tiempo real** (`Properties`, para coeficientes/unidades) sin que exista todavía un
patrón de referencia para eso — ver §9.3 y §9.4 sobre el ADR recomendado.

## 2. Capas afectadas

- [x] API (origen del contrato)
- [x] Web
- [ ] App — diferido, ver [[../../app/APP_DEFERRED]]. Los endpoints de solo-lectura sobre la propia
      unidad (`/me/invoices`, `/me/peace-certificates`) se diseñan pensando en que `PORTAL_RESIDENTE`
      (Web, Fase 1.6) y eventualmente App los consuman, sin rediseño posterior.

## 3. Relación con otras features

- Depende de: [[../PROPIEDADES/PANORAMA]] — usa `properties.id` (unidad a facturar) y
  `property_coefficients` vigentes de tipo `copropiedad` (prorrateo obligatorio, Ley 675). Depende de
  [[../DIRECTORIO/PANORAMA]] — usa `contacts.id` (quién paga, `payment_receipts.contact_id`) y
  `property_occupants`/`es_principal` para saber a quién dirigir por defecto una cuenta de cobro.
- Es consumido por: [[../DASHBOARD/PANORAMA]] — el widget "Cuotas Pendientes" (`billing.ver`,
  hoy `featureStatus: draft` en el dashboard) espera `GET /billing-periods/active/summary`. **Gap de
  contrato detectado por el council (ver §9.4):** este panorama define el summary como
  `GET /condominiums/{id}/billing-periods/active/summary` (consistente con el resto de endpoints
  condominio-scoped de §6), no como una ruta global sin condominio. Requiere que `DASHBOARD`
  actualice su referencia cuando su propio bloque de este widget se ejecute — **no se edita
  `features/DASHBOARD/PANORAMA.md` desde acá** porque esa feature ya está `approved` con bloques
  creados; se deja como acción pendiente explícita para el humano/orquestador correspondiente.
- Es consumido por (futuro):
  - `PAGOS_ONLINE` (Fase 2.1, no diseñada todavía) — extenderá `payment_receipts.medio` (hoy cerrado
    a `efectivo`/`banco`) y, si hace falta, agregará `transaction_id` vía migración aditiva (ver
    decisión revisada en §4 — la columna **no** se incluye en Fase 1, ver §9.3 punto 5).
  - `REPORTES` (Fase 2.8, no diseñada todavía) — construirá reporte de cartera por edades y
    comparativos sobre `invoices`/`payment_receipts`, sin tablas nuevas.
  - `CONTABILIDAD_NIIF` (Fase 3.4, no diseñada todavía) — modelará el fondo de imprevistos como
    cuenta bancaria separada; en esta feature el fondo de imprevistos es solo un `charge_concept`
    (concepto de cobro), no una cuenta.
  - `PORTAL_RESIDENTE` (Fase 1.6, no diseñada todavía) — agregará "mi saldo" sobre `GET /me/invoices`,
    ya expuesto aquí.
  - `PQRS_CUMPLIMIENTO` (Fase 2.6, no diseñada todavía) — **frontera fijada por el council (§9.3
    punto 7):** COBRANZA sigue siendo dueña del artefacto financiero (`invoice_items` con
    `tipo = multa`) para siempre. Cuando `PQRS_CUMPLIMIENTO` exista, se le agrega una FK nullable
    aditiva (`invoice_items.sanction_id` o el inverso) — el cargo financiero no migra de dueño.
- **Explícitamente fuera de esta feature (decisión de scope, mismo espíritu que R-DIR-05 de
  DIRECTORIO):**
  - **Interés de mora** (`late_interest_config`) — presente en el research detallado pero ausente de
    la tabla autoritativa de fases para 1.3. No se diseña ni se modela ahora.
  - **Acuerdos de pago** (`payment_agreements`, `payment_agreement_installments`) — mismo caso.
    Diferido.
  - **Pagos online** (PSE/tarjeta) — `PAGOS_ONLINE`, Fase 2. En Fase 1 el pago es **solo registro
    manual** (efectivo/banco con soporte adjunto), sin pasarela.
  - **Reportes de cartera avanzados** — `REPORTES`, Fase 2.
  - **Contabilidad NIIF** — `CONTABILIDAD_NIIF`, Fase 3.
  - **Sanciones/multas como proceso disciplinario con debido proceso** — pertenecen a
    `PQRS_CUMPLIMIENTO` (Fase 2). Aquí `charge_concepts.tipo = multa` es únicamente el efecto
    financiero, no el proceso sancionatorio.
  - **Flujo de aprobación de pagos (`approval_rules`)** — pertenece a `RBAC_ADMIN` (Fase 2.2). El
    council confirmó (§9.3 punto 6) que RBAC con dos permisos separados basta para Fase 1.

## 4. Modelo de datos

### Decisiones de diseño resueltas (por el Design Council — ver §9 para el proceso completo)

1. **Renombre `unit_id` → `property_id`.** Confirmado sin cambios — `properties` es el término
   canónico de [[../../shared/GLOSSARY]], consistente con `property_occupants` y
   `property_coefficients`. Sin implicación de seguridad (mismo tipo, misma tabla referenciada,
   mismo scope de aislamiento).

2. **`charge_concepts.tipo` cierra su set sin `interes`.** Confirmado — set cerrado de Fase 1:
   `administracion`, `fondo_imprevistos`, `multa`, `extraordinaria`. Agregar `interes` más adelante
   es un cambio de enum aditivo.

3. **`charge_concepts` NO sigue el patrón sistema/personalizado.** Confirmado —
   `charge_concepts.condominium_id` directo y `NOT NULL`, sin catálogo compartido.

4. **`billing_runs.ejecutado_por` y `peace_certificates.emitido_por` reemplazan a `created_by`.**
   Confirmado sin cambios.

5. **`invoice_items` se genera de dos formas** (automática vía `billing_runs`, manual vía
   `POST /invoices/{id}/items`). Confirmado, con una adición: los ítems manuales ahora son
   **editables/eliminables** antes de tener pagos aplicados — ver tabla de endpoints §6 y R-COB-24.

6. **`payment_receipts.transaction_id` — eliminada de Fase 1 (revierte la propuesta original del
   borrador).** El council resolvió, tras peer review, que es sobre-diseño anticipado: con `medio`
   cerrado a `efectivo`/`banco` (R-COB-15), la columna nunca se puebla en esta feature, es una FK sin
   tabla destino y sin test que la ejerza con sentido. Se agrega como migración aditiva (columna +
   FK a `payment_transactions`) cuando `PAGOS_ONLINE` se diseñe — no hay ahorro real de trabajo
   futuro por dejarla ahora, solo una columna muerta que cualquiera que lea `API_DATABASE.md` tendría
   que explicarse.

7. **`invoices.billing_run_id` (nueva, no estaba en el borrador original).** FK a `billing_runs.id`,
   `NOT NULL`. Aunque R-COB-09 garantiza 1:1 en la práctica (un solo `billing_run` `completado` por
   periodo), guardar la referencia directa es barato ahora y caro de agregar después (tocaría todas
   las facturas ya emitidas) — necesario para auditoría/soporte ("¿qué corrida generó esta factura?").

8. **`billing_runs.resumen` (nueva, JSONB nullable).** Reporta éxito parcial de una corrida:
   `{unidades_facturadas: N, unidades_omitidas: N, detalle_omitidas: [{property_id, motivo}]}`. Sin
   esto, `billing_runs.estado` (`en_proceso`/`completado`/`fallido`) no puede distinguir "completado
   con todas las unidades" de "completado pero 5 unidades se saltearon por falta de coeficiente
   vigente" — un hallazgo real del council (lente UX, confirmado por arquitectura) que el borrador
   original no contemplaba.

9. **Generación de `peace_certificates.pdf_url` — síncrona en Fase 1 (resuelve la ambigüedad del
   borrador original).** El borrador decía "puede poblarse tras generación asíncrona" sin definir
   estado intermedio. El council resolvió que, al ser una operación de bajo volumen (un documento a
   la vez, no un batch de cientos de filas como `billing_runs`), no amerita la complejidad de un
   estado asíncrono: `POST /properties/{id}/peace-certificates` no responde hasta tener `pdf_url`
   poblado. Evita inventar una máquina de estados nueva (`pdf_status`) para un caso que no la
   necesita — mismo criterio anti-sobre-diseño que ya eliminó `transaction_id`.

### Tablas nuevas (8, alcance exacto de la tabla de fases 1.3)

Convenciones de columnas: [[../../shared/DATA_MODEL]] §1. Auditoría (`created_by`/`updated_by`):
§1-bis (vigente desde `PROPIEDADES`, se mantiene aquí). Dinero en `NUMERIC(15,2)` COP; coeficiente en
`decimal(5,4)` (mismo tipo que `property_coefficients.valor` en PROPIEDADES).

| Entidad | Nueva/Existente | Campo | Valor/Referencia | Notas |
|---|---|---|---|---|
| `charge_concepts` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL. Sin nivel de catálogo compartido (decisión 3) |
| | | `nombre` | Valor (text) | NOT NULL. `UNIQUE(condominium_id, nombre) WHERE deleted_at IS NULL` |
| | | `tipo` | Valor (text, enum cerrado) | NOT NULL. Set: `administracion`, `fondo_imprevistos`, `multa`, `extraordinaria` (decisión 2) |
| | | `metodo_calculo` | Valor (text, enum cerrado) | NOT NULL. Set: `coeficiente`, `fijo`, `por_area`, `manual` |
| | | `valor_base` | Valor (NUMERIC(15,2)) | NOT NULL. Significado depende de `metodo_calculo` |
| | | `activo` | Valor (bool) | Default `true`. Inactivo no participa en nuevos `billing_runs` ni aplicación manual |
| | | `created_by` / `updated_by` | Referencia (`→ users.id`, nullable) | R-11 heredada |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar |
| `billing_periods` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL |
| | | `anio` | Valor (int) | NOT NULL |
| | | `mes` | Valor (int, 1-12) | NOT NULL. `UNIQUE(condominium_id, anio, mes) WHERE deleted_at IS NULL` |
| | | `estado` | Valor (text, enum cerrado) | NOT NULL. Set: `abierto`, `facturado`, `cerrado`. Default `abierto` |
| | | `created_by` / `updated_by` | Referencia (`→ users.id`, nullable) | R-11 |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar |
| `billing_runs` | Nueva | `id` | Valor (UUID v7 PK) | |
| | | `billing_period_id` | Referencia (`→ billing_periods.id`) | NOT NULL. **`UNIQUE(billing_period_id) WHERE estado = 'completado' AND deleted_at IS NULL`** — constraint de BD, no solo regla de aplicación (endurece R-COB-09, hallazgo del council) |
| | | `ejecutado_por` | Referencia (`→ users.id`) | NOT NULL. Actor obligatorio (decisión 4) |
| | | `fecha` | Valor (timestamptz) | NOT NULL |
| | | `estado` | Valor (text, enum cerrado) | NOT NULL. Set: `en_proceso`, `completado`, `fallido` |
| | | `resumen` | Valor (JSONB, nullable) | Ver decisión 8 — conteo de unidades facturadas/omitidas |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | Transición de estado |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar |
| `invoices` | Nueva | `id` | Valor (UUID v7 PK) | La "cuenta de cobro" |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL |
| | | `property_id` | Referencia (`→ properties.id`) | NOT NULL (decisión 1) |
| | | `billing_period_id` | Referencia (`→ billing_periods.id`) | NOT NULL |
| | | `billing_run_id` | Referencia (`→ billing_runs.id`) | NOT NULL (decisión 7, nueva) |
| | | `numero` | Valor (text) | NOT NULL. `UNIQUE(condominium_id, numero)` |
| | | `fecha_emision` | Valor (date) | NOT NULL |
| | | `fecha_vencimiento` | Valor (date) | NOT NULL. **Índice** para el cálculo derivado de `vencida` (ver R-COB-08 revisada) |
| | | `valor_total` | Valor (NUMERIC(15,2)) | NOT NULL. Suma de `invoice_items.valor` |
| | | `saldo` | Valor (NUMERIC(15,2)) | NOT NULL. Se recalcula al aplicar `payment_allocations` bajo lock (R-COB-21) |
| | | `estado` | Valor (text, enum cerrado, **derivado en lectura, no almacenado**) | Set: `pendiente`, `parcial`, `pagada`, `vencida` — ver decisión revisada de R-COB-08 |
| | | `created_by` / `updated_by` | Referencia (`→ users.id`, nullable) | R-11 |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar. **Índices:** `(property_id, estado)`, `(condominium_id, billing_period_id)` |
| `invoice_items` | Nueva | `id` | Valor (UUID v7 PK) | Renglón de la cuenta de cobro |
| | | `invoice_id` | Referencia (`→ invoices.id`) | NOT NULL. **Índice** |
| | | `charge_concept_id` | Referencia (`→ charge_concepts.id`) | NOT NULL |
| | | `descripcion` | Valor (text, nullable) | Libre |
| | | `valor` | Valor (NUMERIC(15,2)) | NOT NULL |
| | | `base_calculo` | Valor (decimal(5,4), nullable) | **Snapshot inmutable** (confirmado, R-COB-06 sin cambios) |
| | | `created_by` / `updated_by` | Referencia (`→ users.id`, nullable) | R-11 |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar. `deleted_at`/edición solo si `charge_concept.metodo_calculo = manual` y sin `payment_allocations` (R-COB-24) |
| `payment_receipts` | Nueva | `id` | Valor (UUID v7 PK) | El recibo/comprobante de pago |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL |
| | | `property_id` | Referencia (`→ properties.id`) | NOT NULL |
| | | `contact_id` | Referencia (`→ contacts.id`) | NOT NULL. Party, nunca `user_id` (ADR-001 §3, confirmado) |
| | | `valor` | Valor (NUMERIC(15,2)) | NOT NULL |
| | | `fecha` | Valor (date) | NOT NULL |
| | | `medio` | Valor (text, enum cerrado) | NOT NULL. Fase 1: solo `efectivo`, `banco` |
| | | `referencia` | Valor (text, nullable) | Dato financiero de bajo nivel — no se loguea en texto plano (R-COB-26) |
| | | `soporte_url` | Valor (text, nullable) | Adjunto — origen y validación definidos en R-COB-27 |
| | | ~~`transaction_id`~~ | — | **Eliminada de Fase 1** (decisión 6 revisada) |
| | | `created_by` | Referencia (`→ users.id`) | NOT NULL. Quién registró (R-COB-13) |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | Quién anuló/corrigió |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar. `deleted_at` = anulación (R-COB-13) |
| `payment_allocations` | Nueva | `id` | Valor (UUID v7 PK) | Aplicación de un pago a una o varias cuentas de cobro |
| | | `payment_receipt_id` | Referencia (`→ payment_receipts.id`) | NOT NULL |
| | | `invoice_id` | Referencia (`→ invoices.id`) | NOT NULL. **Índice** — hot path de recálculo de `saldo` |
| | | `valor_aplicado` | Valor (NUMERIC(15,2)) | NOT NULL. Suma exacta al 100% del recibo (R-COB-23, revisada) |
| | | `created_by` | Referencia (`→ users.id`, nullable) | R-11 |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar. Inmutable en la práctica (R-COB-14) |
| `peace_certificates` | Nueva | `id` | Valor (UUID v7 PK) | Certificado de paz y salvo |
| | | `condominium_id` | Referencia (`→ condominiums.id`) | NOT NULL |
| | | `property_id` | Referencia (`→ properties.id`) | NOT NULL |
| | | `emitido_por` | Referencia (`→ users.id`) | NOT NULL (decisión 4) |
| | | `numero` | Valor (text) | NOT NULL. `UNIQUE(condominium_id, numero)` |
| | | `fecha` | Valor (date) | NOT NULL |
| | | `vigente_hasta` | Valor (date, nullable) | `NULL` = sin vencimiento definido |
| | | `pdf_url` | Valor (text, nullable) | Poblado sincrónicamente antes de responder (decisión 9, revisada) |
| | | `updated_by` | Referencia (`→ users.id`, nullable) | Revocación — ver R-COB-28 (`DELETE` = revocar, soft-delete existente reutilizado, no campo nuevo) |
| | | `created_at` / `updated_at` / `deleted_at` | Valor (timestamptz) | Estándar. `deleted_at` = revocado (R-COB-28) |

## 5. Reglas de negocio globales

Reglas heredadas del borrador original (sin cambios salvo donde se indica):

- **R-COB-01 — Tenant isolation:** toda query scopea por `condominium_id`, mismo criterio de RLS que
  el resto del sistema (ADR-001 §1).
- **R-COB-02 — Aislamiento por scope de staff:** un `role_assignment.scope_type ∈ {condominium,
  tower}` limita qué conceptos/periodos/facturas/pagos gestiona un usuario staff. Datos financieros
  requieren scope `condominium` u `organization` como mínimo.
- **R-COB-03 — Scope de residente:** un residente autenticado solo ve las
  `invoices`/`payment_receipts`/`peace_certificates` de sus unidades (`property_occupants` activo).
- **R-COB-04 — Prorrateo obligatorio por coeficiente (Ley 675):** sin cambios.
- **R-COB-05 — `billing_run` solo cubre unidades activas.**
- **R-COB-06 — Snapshot inmutable de `base_calculo`:** confirmado sin cambios (§9.3 punto 3).
- **R-COB-07 — Conceptos manuales fuera del `billing_run`.**
- **R-COB-08 — `invoices.estado = vencida` es derivado en lectura, no almacenado (revisada).**
  `estado = CASE WHEN saldo = 0 THEN 'pagada' WHEN fecha_vencimiento < CURRENT_DATE AND saldo > 0
  THEN 'vencida' WHEN saldo < valor_total THEN 'parcial' ELSE 'pendiente' END`, calculado en el
  backend al serializar (no solo client-side), para que Web y el futuro Portal/App coincidan sin
  duplicar lógica de fecha. No existe scheduler en la infraestructura (`API_BOOTSTRAP` no lo define)
  — construir uno solo para mantener un campo de lectura sería invertir infraestructura nueva para
  algo que una expresión de consulta con índice resuelve sin estado que se desincronice.
- **R-COB-09 — Un solo `billing_run` completado por periodo:** ahora reforzado con constraint de BD
  (ver tabla `billing_runs` en §4), no solo verificación de aplicación.
- **R-COB-10 — Ciclo de vida de `billing_periods`:** `abierto → facturado → cerrado`. Cerrar con
  facturas pendientes/parciales **no bloquea** (warning no bloqueante — ver R-COB-08-bis abajo).
- **R-COB-11 — Paz y salvo condicionado a saldo cero.**
- **R-COB-12 — Un pago cubre una o varias facturas** vía `payment_allocations` — ver R-COB-23 sobre
  el límite exacto de distribución.
- **R-COB-13 — Segregación de funciones en pagos:** `pagos.registrar` ≠ `pagos.anular` — confirmado
  que RBAC nuevo basta, sin `approval_rules` (§9.3 punto 6). El seed de roles de sistema debe
  garantizar que ambos permisos no coincidan por defecto en el mismo rol para condominios con
  separación real de personal (ej. "auxiliar contable" con solo `pagos.registrar`).
- **R-COB-14 — Inmutabilidad de `payment_allocations`.**
- **R-COB-15 — Medio de pago cerrado en Fase 1:** `efectivo`/`banco` únicamente. Sin `transaction_id`
  (columna eliminada, decisión 6 revisada).
- **R-COB-16 — Soft delete universal y auditoría.**
- **R-COB-17 — Catálogo de conceptos no compartido entre condominios.**
- **R-COB-18 — Fondo de imprevistos: validación de la regla ≥1% diferida, con advertencia visible.**
  Se agrega `warnings[]` en `POST /charge-concepts` cuando `tipo = fondo_imprevistos`, avisando que
  la validación de mínimo legal no está implementada en esta fase — para que ningún administrador de
  la demo asuma que el sistema ya la garantiza (refinamiento del council sobre la nota original).
- **R-COB-19 — Fuera de alcance explícito:** `late_interest_config`, `payment_agreements`,
  `payment_agreement_installments`.

Reglas nuevas producidas por el Design Council:

- **R-COB-08-bis — Cerrar periodo con facturas pendientes/parciales:** warning no bloqueante (no
  `409`), reutilizando el mecanismo `warnings[]` ya estandarizado (mismo patrón que
  `COEFFICIENT_SUM_MISMATCH` de PROPIEDADES) con código `BILLING_PERIOD_HAS_PENDING_INVOICES`. El
  diálogo de confirmación en Web exige un checkbox explícito de "Entiendo que quedarán facturas
  pendientes abiertas" antes de habilitar el cierre.
- **R-COB-20 — Anti-enumeración 403/404 unificado en endpoints de residente:** `GET
  /me/invoices/{id}` y `GET /me/peace-certificates` deben responder `404` uniforme (nunca `403`
  revelando existencia) cuando el recurso no pertenece a ninguna unidad del residente autenticado —
  mismo patrón ya implementado en PROPIEDADES para prevenir enumeración. R-COB-03 ya prohibía el
  acceso; esta regla fija el comportamiento observable exacto.
- **R-COB-21 — Locking en aplicación de pagos:** `POST /payment-receipts` debe aplicar `SELECT ...
  FOR UPDATE` (o equivalente) sobre las `invoices` afectadas dentro de la misma transacción que
  inserta `payment_allocations` y recalcula `saldo`, para prevenir que dos pagos casi simultáneos
  sobre la misma factura corrompan el saldo resultante.
- **R-COB-22 — `billing_run` es asíncrono:** `POST /billing-periods/{id}/billing-runs` responde
  `202` de inmediato (el `billing_run` queda `en_proceso`) y el prorrateo real corre en un Job
  encolado (`jobs`/`job_batches`, ya disponibles desde `API_BOOTSTRAP`). El cliente hace polling de
  `GET /billing-runs/{id}` hasta `completado`/`fallido`. Es la primera vez que el contrato de API
  necesita el patrón "202 + polling" — se documenta como convención general nueva en
  `api/API_CONTRACT.md` cuando el bloque correspondiente se ejecute, no como excepción puntual de
  COBRANZA.
- **R-COB-23 — Distribución exacta de `payment_allocations`:** la suma de `valor_aplicado` de un
  mismo `payment_receipt` debe ser exactamente igual a `payment_receipts.valor` — Fase 1 no modela
  "saldo a favor"/crédito no aplicado. Si un pago no puede distribuirse al 100% contra facturas con
  saldo pendiente, la API rechaza la operación con un error accionable (el usuario debe ajustar el
  monto o seleccionar más facturas). Modelar crédito a favor del contacto queda diferido a una
  extensión futura si se confirma como requisito real.
- **R-COB-24 — Corrección de conceptos manuales:** un `invoice_items` con
  `charge_concept.metodo_calculo = manual` es editable/eliminable vía `PATCH`/`DELETE
  /invoice-items/{id}` únicamente si su `invoice` padre no tiene ninguna `payment_allocations`
  aplicada — mismo espíritu de inmutabilidad progresiva que R-COB-14.
- **R-COB-25 — Idempotencia en registro de pagos:** `POST /payment-receipts` debe prevenir
  duplicados por reintento de red (ej. header `Idempotency-Key`, o rechazo de un recibo idéntico en
  `contact_id`+`valor`+`fecha`+`property_id` dentro de una ventana corta) — un control clásico de
  integridad financiera que ninguna de las 3 lentes había cubierto en su primera pasada y surgió
  recién en peer review.
- **R-COB-26 — Datos sensibles en logs:** ningún log de aplicación (errores, requests) vuelca
  `valor`, `valor_aplicado`, ni `referencia` en texto plano sin redacción.
- **R-COB-27 — Rate limiting defensivo:** `POST /payment-receipts` y la generación de
  `peace-certificates` llevan throttle moderado (ej. `throttle:60,1` por usuario) como segunda línea
  de defensa (la primera es RBAC + auditoría de `created_by`). El endpoint real de subida detrás de
  `soporte_url` (no definido en el borrador original, debe cerrarse antes de crear bloques) valida
  tipo de archivo (imagen/PDF) y tamaño máximo, y el resultado se sirve desde storage propio del
  tenant (nunca una URL de tercero aceptada tal cual, para prevenir SSRF si algún proceso backend
  llega a resolverla).
- **R-COB-28 — Revocación de paz y salvo:** se agrega `DELETE /peace-certificates/{id}` (requiere
  permiso `cobranza.paz_salvo.revocar`, distinto de `cobranza.paz_salvo.generar`) — reutiliza el
  soft-delete existente (`deleted_at`, `updated_by`) en vez de un campo de estado nuevo. Cierra la
  inconsistencia del borrador original (que mencionaba revocación en `updated_by` sin exponer cómo
  se dispara).
- **R-COB-29 — Advertencia no bloqueante en conceptos `extraordinaria` duplicados:** no hay
  restricción de unicidad dura (el caso real de "cuota fachada" + "cuota ascensor" en paralelo es
  legítimo), pero el formulario de creación de un concepto `tipo = extraordinaria` muestra los
  conceptos del mismo tipo ya activos, para que el administrador confirme a ojo que no está
  duplicando por error de tipeo.
- **R-COB-30 — Nomenclatura del bounded context:** el código de esta feature vive en `src/Billing/`
  (no `src/Cobranza/`), consistente con los bounded contexts ya implementados en inglés (`Auth`,
  `Authorization`, `Mfa`, `Properties`). Es una decisión de nomenclatura de código, documentada aquí
  para que el bloque fundacional no tenga que adivinarla.

### Catálogo de permisos RBAC (nombrado explícitamente — hueco cerrado por el council)

El borrador original solo nombraba `pagos.registrar`/`pagos.anular`. El catálogo completo, todos con
scope mínimo `condominium`/`organization` (nunca `tower` para escritura financiera, R-COB-02):

| Permiso | Acción que gatea |
|---|---|
| `billing.ver` | **Ya existe** (creado por `DASHBOARD` para gatear su widget de navegación) — se reutiliza como el permiso "de entrada" que habilita ver que el módulo Cobranza existe (nav, widgets). No se crea un permiso nuevo redundante. |
| `cobranza.conceptos.ver` / `cobranza.conceptos.gestionar` | Ver / crear-editar-desactivar `charge_concepts` |
| `cobranza.periodos.ver` | Ver periodos y su resumen de cartera (`GET .../summary`) |
| `cobranza.facturacion.ejecutar` | Abrir periodo y **correr** una corrida de facturación — separado de solo-ver, es la acción de mayor impacto del feature |
| `cobranza.facturas.ver` / `cobranza.facturas.gestionar` | Ver facturas / agregar ítem manual, corregir ítem manual (R-COB-24) |
| `pagos.registrar` / `pagos.anular` | Registrar pago / anular pago (R-COB-13) — nunca en el mismo rol de sistema por defecto |
| `cobranza.paz_salvo.generar` / `cobranza.paz_salvo.revocar` | Generar / revocar (R-COB-28) — nunca en el mismo rol de sistema por defecto |

## 6. Mapeo de acciones a endpoints (alto nivel)

El detalle de request/response vive en `api/endpoints/COBRANZA.md` — aquí solo el mapeo.

### Conceptos de cobro

| Acción del usuario | Verbo | Endpoint | Permiso |
|---|---|---|---|
| Listar conceptos de cobro del condominio | GET | `/condominiums/{id}/charge-concepts` | `cobranza.conceptos.ver` |
| Ver concepto de cobro | GET | `/charge-concepts/{id}` | `cobranza.conceptos.ver` |
| Crear concepto de cobro | POST | `/condominiums/{id}/charge-concepts` | `cobranza.conceptos.gestionar` |
| Editar concepto de cobro | PATCH | `/charge-concepts/{id}` | `cobranza.conceptos.gestionar` |
| Desactivar/eliminar concepto de cobro | DELETE | `/charge-concepts/{id}` | `cobranza.conceptos.gestionar` |

### Periodos y facturación

| Acción del usuario | Verbo | Endpoint | Permiso |
|---|---|---|---|
| Listar periodos de facturación | GET | `/condominiums/{id}/billing-periods` | `cobranza.periodos.ver` |
| Abrir un nuevo periodo | POST | `/condominiums/{id}/billing-periods` | `cobranza.facturacion.ejecutar` |
| Ver periodo | GET | `/billing-periods/{id}` | `cobranza.periodos.ver` |
| Cerrar periodo | PATCH | `/billing-periods/{id}` (body: `{estado: cerrado}`, puede incluir `warnings[]` — R-COB-08-bis) | `cobranza.facturacion.ejecutar` |
| Generar facturación del periodo (asíncrono, R-COB-22) | POST | `/billing-periods/{id}/billing-runs` → `202` | `cobranza.facturacion.ejecutar` |
| Ver corridas de facturación de un periodo | GET | `/billing-periods/{id}/billing-runs` | `cobranza.periodos.ver` |
| Ver detalle de una corrida (polling, incluye `resumen`) | GET | `/billing-runs/{id}` | `cobranza.periodos.ver` |
| Panel de cartera del periodo activo (**endpoint reconciliado con DASHBOARD, ver §3/§9.4**) | GET | `/condominiums/{id}/billing-periods/active/summary` | `billing.ver` |
| Panel de cartera de un periodo específico | GET | `/billing-periods/{id}/summary` | `cobranza.periodos.ver` |

### Cuentas de cobro

| Acción del usuario | Verbo | Endpoint | Permiso |
|---|---|---|---|
| Listar cuentas de cobro del condominio | GET | `/condominiums/{id}/invoices` (`?property_id=&billing_period_id=&estado=&search=`) | `cobranza.facturas.ver` |
| Ver detalle de cuenta de cobro (incluye `payment_allocations[]` anidado) | GET | `/invoices/{id}` | `cobranza.facturas.ver` |
| Agregar concepto manual a una cuenta de cobro | POST | `/invoices/{id}/items` | `cobranza.facturas.gestionar` |
| Corregir/eliminar concepto manual (R-COB-24) | PATCH / DELETE | `/invoice-items/{id}` | `cobranza.facturas.gestionar` |
| Ver mi estado de cuenta (residente) | GET | `/me/invoices` | autenticado, R-COB-03 |
| Ver detalle de mi cuenta de cobro (R-COB-20: 404 uniforme) | GET | `/me/invoices/{id}` | autenticado, R-COB-03 |

### Pagos

| Acción del usuario | Verbo | Endpoint | Permiso |
|---|---|---|---|
| Listar pagos/abonos del condominio | GET | `/condominiums/{id}/payment-receipts` | `cobranza.facturas.ver` |
| Registrar pago/abono manual (idempotente, R-COB-25) | POST | `/payment-receipts` (body incluye `payment_allocations[]`) | `pagos.registrar` |
| Ver detalle de un pago | GET | `/payment-receipts/{id}` | `cobranza.facturas.ver` |
| Anular un pago | DELETE | `/payment-receipts/{id}` | `pagos.anular` |

### Paz y salvo

| Acción del usuario | Verbo | Endpoint | Permiso |
|---|---|---|---|
| Generar paz y salvo de una unidad (síncrono, decisión 9) | POST | `/properties/{id}/peace-certificates` | `cobranza.paz_salvo.generar` |
| Listar paz y salvo de una unidad | GET | `/properties/{id}/peace-certificates` | `cobranza.facturas.ver` |
| Ver/descargar un paz y salvo | GET | `/peace-certificates/{id}` | `cobranza.facturas.ver` |
| Revocar un paz y salvo (R-COB-28, nuevo) | DELETE | `/peace-certificates/{id}` | `cobranza.paz_salvo.revocar` |
| Ver mi paz y salvo (residente, R-COB-20: 404 uniforme) | GET | `/me/peace-certificates` | autenticado, R-COB-03 |

## 7. Plan de bloques

El detalle de bloques vive en [[BLOCKS]] (mismo directorio que este panorama) — 11 bloques (`api`
B01-B06, `web` B07-B11), cadena API estrictamente secuencial. Ver también
[[../../shared/adr/ADR-002-lectura-cross-context-modulo-monolito]], prerrequisito de diseño resuelto
para que `COBRANZA-B01` pueda leer `Properties` en tiempo real.

## 8. Checklist de aprobación (gate)

- [x] §4: cada campo nuevo declara Valor o Referencia
- [x] §6 cubre toda acción visible al usuario descrita en §1/§5
- [x] Nombres de campos y entidades consistentes con [[../../shared/GLOSSARY]] — términos nuevos
      agregados: "Concepto de cobro", "Periodo de facturación", "Corrida de facturación (billing
      run)", "Cuenta de cobro (Invoice)", "Recibo de pago", "Aplicación de pago (payment
      allocation)", "Paz y salvo", "Fondo de imprevistos" (como concepto de cobro)
- [x] No hay una feature existente en `features/` que ya cubra esto (revisar `_state/BOARD.md`) —
      confirmado, solo `AUTH`/`API_BOOTSTRAP`/`WEB_BOOTSTRAP`/`PROPIEDADES`/`DIRECTORIO`/`DASHBOARD`
      existen
- [ ] **Acción pendiente cross-feature (no es parte de este gate, sigue abierta — se resuelve antes o
      junto con el bloque de Web que construya el widget de DASHBOARD):** reconciliar
      `features/DASHBOARD/PANORAMA.md` §4 (que referencia `GET /billing-periods/active/summary`) con
      el endpoint real definido acá (`GET /condominiums/{id}/billing-periods/active/summary`) — ver
      §9.4 y [[BLOCKS#Acción pendiente cross-feature (no bloquea esta cadena)]].

> **Aprobado (2026-07-09)** por el gate humano de `_system/03_LIFECYCLE.md` §3 — `estado_diseño:
> approved` en el frontmatter. Partición en bloques completa, ver §7 y [[BLOCKS]].

## 9. Veredicto del Design Council

### 9.1 Proceso

El Design Council ejecutó el protocolo de 3 fases documentado en `_system/06_AGENT_ROLES.md` §12,
sobre el borrador extenso ya existente (research + modelo de datos + reglas de negocio, con 10
puntos de divergencia explícitos en su antiguo §9):

1. **Divergencia (2026-07-09):** tres análisis independientes en paralelo, cada uno auditando el
   borrador completo desde su lente — arquitectura/datos/endpoints/escalabilidad, UX/flujos/
   pantallas/accesibilidad, seguridad/autorización/datos sensibles.
2. **Peer Review anonimizada (2026-07-09):** los 3 análisis se anonimizaron como Diseño A/B/C. Cada
   lente evaluó los 3 (incluyendo el propio, sin saber cuál era) y produjo ranking, fortalezas,
   debilidades y puntos ciegos propios. El revisor de seguridad, en particular, verificó
   afirmaciones contra el repo real (confirmó el gap de contrato con `DASHBOARD`, el permiso
   `billing.ver` ya existente, y la ausencia del patrón 202/polling en `API_CONTRACT.md`) antes de
   rankear — elevando el peer review de opinión a verificación.
3. **Síntesis (2026-07-09):** consolidación de los 3 análisis + 3 peer reviews en este documento.

### 9.2 Convergencias

Las 3 lentes coincidieron, sin que se les pidiera explícitamente, en:

| Decisión | Arquitectura | UX | Seguridad |
|---|---|---|---|
| `billing_run` no reversible una vez `completado` | Sí | Sí (solo si 0 pagos aplicados) | — |
| Cerrar periodo con pendientes: warning no bloqueante, no `409` | Sí | Sí | — |
| `transaction_id`: no debe ser input de usuario en Fase 1 | Sí (eliminar columna) | — | Sí (columna o no, nunca input) |
| Segregación de funciones: RBAC nuevo basta, no `approval_rules` | Sí | — | Sí |
| Renombre `unit_id`→`property_id`: sin objeción | Sí | — | Sí |
| Facturación masiva necesita ser asíncrona (202+polling) | Sí | Sí (banner persistente, nunca spinner bloqueante) | — |
| `vencida` derivado en lectura, no almacenado (no hay scheduler) | Sí | Sí (calculado server-side) | — |

### 9.3 Divergencias resueltas

| Tema | Posturas | Resolución adoptada |
|---|---|---|
| **Nombre del bounded context** | Arquitectura: `src/Billing/`, nunca `src/Cobranza/` (código real usa inglés). UX/Seguridad no opinaron. | `src/Billing/` — R-COB-30. Consistente con `Auth`/`Authorization`/`Mfa`/`Properties` ya implementados. |
| **Éxito parcial de `billing_run`** | UX lo detectó como hueco de UI (no se puede explicar por qué el conteo final no coincide con el estimado); Arquitectura lo confirmó como hueco de modelo de datos, no solo de respuesta de API. | `billing_runs.resumen` (JSONB) nuevo — decisión 8 de §4. |
| **"Saldo a favor" no modelado** | UX: gap real, propone exigir suma exacta como paliativo. Seguridad, en su propia auto-crítica de peer review, reconoció que es también un control de integridad financiera (crédito fantasma) que debió cubrir. | R-COB-23: distribución debe sumar exactamente el 100% del recibo. Modelar crédito a favor queda diferido — no se construye especulativamente. |
| **Revocación de paz y salvo** | UX detectó la inconsistencia (modelo menciona `updated_by` para revocación, `§6` original no tenía endpoint). Seguridad, en su auto-crítica, reconoció que debió levantarlo él mismo por ser un artefacto legal. | R-COB-28: `DELETE /peace-certificates/{id}` reutiliza el soft-delete existente — sin campo de estado nuevo. |
| **Permisos RBAC no nombrados** | Seguridad detectó que solo `pagos.*` estaba nombrado; Arquitectura no auditó el catálogo completo en su primera pasada. | Catálogo completo nombrado en §5, reconciliando con `billing.ver` (ya creado por `DASHBOARD`) en vez de crear un permiso de entrada redundante. |
| **Generación de PDF de paz y salvo: síncrona o asíncrona** | Borrador original: ambiguo ("puede poblarse tras generación asíncrona"). UX señaló la ambigüedad sin resolverla (recomendó `pdf_status`/timeout como paliativo). | Síncrona en Fase 1 (decisión 9 de §4) — bajo volumen, no amerita máquina de estados nueva. Evita la complejidad que proponía el paliativo de UX. |
| **Concurrencia en `invoices.saldo`** | Solo Arquitectura lo detectó en primera pasada; ni UX ni Seguridad lo tenían en su lista original, aunque Seguridad lo reconoció como blind spot propio en peer review ("territorio de seguridad de datos que no mencioné"). | R-COB-21: `SELECT ... FOR UPDATE` dentro de la transacción de `POST /payment-receipts`. |

### 9.4 Puntos ciegos detectados en peer review

1. **Gap de contrato con `DASHBOARD` (`GET /billing-periods/active/summary` vs.
   `/billing-periods/{id}/summary`) — detectado por UX, verificado por Seguridad contra el repo
   real.** No es un punto ciego de una sola lente — ninguna de las 3 auditó el consumo cruzado desde
   otras features en su primera pasada. **No se resuelve unilateralmente editando
   `features/DASHBOARD/PANORAMA.md`** porque esa feature ya está `approved` con bloques creados
   (`DASHBOARD-B01` en `ready`). Queda documentado en §3/§6/§8 como acción pendiente explícita para
   el humano: decidir si el bloque de Web que construye el widget de cartera en `DASHBOARD` ajusta su
   referencia al endpoint real (`/condominiums/{id}/billing-periods/active/summary`), o si se agrega
   un alias sin condominio por conveniencia del dashboard. No es bloqueante para aprobar este
   panorama — es aceptable como deuda de coordinación documentada, mismo criterio que otras features
   ya usaron para gaps entre fases.
2. **Idempotencia en `POST /payment-receipts`** — ninguna de las 3 lentes lo cubrió en su primera
   pasada (Seguridad lo reconoció como blind spot en su propia auto-crítica). Aceptable resolverlo
   como criterio de aceptación del bloque de implementación (R-COB-25) sin rediseño de modelo.
3. **Endpoint de upload real detrás de `soporte_url`** — el borrador original y las 3 lentes asumen
   que la URL ya existe sin definir dónde se sube el archivo. Queda como punto a cerrar en el bloque
   correspondiente (R-COB-27), no bloqueante para el panorama porque no cambia el modelo de datos de
   COBRANZA en sí (el endpoint de upload es infraestructura compartida, potencialmente de
   `API_BOOTSTRAP` o un bloque propio).
4. **Autorización en la lectura cross-bounded-context** (COBRANZA leyendo `Properties` en tiempo
   real) — Arquitectura lo marcó como candidato a ADR sin resolverlo; Seguridad, en su auto-crítica,
   reconoció que debió preguntar con qué identidad/scope se ejecuta esa lectura. **Se recomienda un
   ADR dedicado** (`shared/adr/ADR-00X-cross-context-read-access.md`) antes de que el bloque
   fundacional de COBRANZA (equivalente a `PROPIEDADES-B01`) se ejecute — no se resuelve solo inline
   en este panorama porque, como bien señaló Arquitectura, es una decisión que se va a repetir en
   toda feature futura que dependa de otra (`PORTERIA`, `PQRS_CUMPLIMIENTO`, `REPORTES`), no algo
   específico de COBRANZA. Postura tentativa para no bloquear: consulta directa de solo-lectura a las
   tablas de `Properties` (sin importar sus clases de aplicación) en Fase 1, heredando el mismo scope
   de `condominium_id` ya vigente — el ADR puede confirmar o revisar esto.

### 9.5 Recomendación del council

El Design Council recomienda **proceder con este diseño** — los 10 puntos de divergencia originales
quedaron resueltos con postura explícita y justificación (§9.3, más las convergencias de §9.2), y los
4 puntos ciegos adicionales detectados en peer review (§9.4) son deuda técnica documentada, no
bloqueantes: ninguno exige rediseñar el modelo de 8 tablas ya validado, y el más consecuente (el ADR
de lectura cross-context) tiene una postura tentativa segura que no impide arrancar el bloque
fundacional. El gap de contrato con `DASHBOARD` requiere coordinación humana explícita antes de que
el widget de cartera se construya, pero no antes de aprobar este panorama.
