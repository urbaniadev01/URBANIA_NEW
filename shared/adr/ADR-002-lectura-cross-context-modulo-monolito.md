---
tipo: adr
proyecto: shared
actualizado: 2026-07-09
---

# ADR-002: Lectura cross-bounded-context en el monolito modular (solo lectura, mismo scope de tenant)

## Estado

Aceptada.

## Contexto

`COBRANZA` es la primera feature del vault que necesita leer datos de otro bounded context ya
implementado (`Properties`, para `property_id` y `property_coefficients` vigentes al prorratear) en
tiempo real, sin que exista todavía un patrón de referencia — detectado como punto ciego en el
Design Council de `COBRANZA` (ver [[../../features/COBRANZA/PANORAMA#9.4 Puntos ciegos detectados en peer review]]
punto 4). La pregunta no es específica de `COBRANZA`: toda feature futura que dependa de otra
(`PORTERIA` sobre `Properties`/`Directorio`, `PQRS_CUMPLIMIENTO` sobre `Directorio`, `REPORTES` sobre
`Billing`) va a repetirla. Opciones reales consideradas:

1. **Lectura directa de los modelos Eloquent del otro contexto** (`Properties\Models\Property`,
   `Properties\Models\PropertyCoefficient` importados desde `Billing`), sin pasar por una capa de
   aplicación/servicio del contexto dueño.
2. **Capa de servicio interno por contexto** (`PropertiesReadService` expuesto por `Properties`,
   consumido por `Billing` vía inyección de dependencias) — misma base de datos, pero con un
   contrato de código explícito en vez de acceso directo al modelo.
3. **Réplica de solo-lectura vía eventos** (`Billing` mantiene su propia copia mínima de
   `property_id`/coeficiente, sincronizada por eventos de dominio de `Properties`) — patrón típico de
   microservicios con bases de datos separadas.
4. **Llamada HTTP interna** (`Billing` consume el propio `API_CONTRACT.md` de `Properties` vía
   cliente HTTP) — trata los contextos como si fueran servicios independientes.

## Decisión

Mientras `code/api/` sea **un monolito Laravel con un único proceso y una única base de datos**
(confirmado en `API_BOOTSTRAP` y sin ADR que lo cambie), un bounded context puede leer — nunca
escribir — modelos Eloquent de otro bounded context directamente, siempre que:

- La lectura sea **read-only**: solo queries (`::query()`, `find`, relaciones `belongsTo`/`hasMany`
  ya definidas en el modelo dueño) — nunca `save()`, `update()`, `create()`, ni `delete()` sobre un
  modelo que no pertenece al propio contexto.
- El scope de aislamiento sea **exactamente el mismo que ya rige la request** — `condominium_id`
  heredado del middleware de tenant (ADR-001 §1), nunca un scope ampliado "porque ya se tiene el
  modelo a mano".
- La lectura viva en la capa de aplicación del contexto consumidor (ej.
  `Billing\Application\UseCases\RunBillingPeriod` puede leer `PropertyCoefficient`), nunca en el
  dominio puro (`Billing\Domain`) — el dominio de `Billing` no depende de clases de `Properties`,
  solo su capa de aplicación las orquesta.

Se descarta la capa de servicio interno (opción 2) y la réplica por eventos (opción 3) para Fase 1
por sobre-ingeniería: ambas resuelven un problema de límites de servicio que no existe todavía en un
monolito de un solo proceso — se pagan cuando `Properties` (o cualquier contexto) se separe en un
servicio propio, no antes. Se descarta la llamada HTTP interna (opción 4) por el mismo motivo, con el
costo adicional de latencia/complejidad de red dentro del mismo proceso.

## Consecuencias

- **Se gana:** cero infraestructura nueva, cero indirección para el caso común (leer coeficientes
  vigentes de una unidad al facturar). Los bloques `COBRANZA-B03`/`B04` (que ejecutan la corrida de
  facturación) importan directamente `Properties\Models\Property` y
  `Properties\Models\PropertyCoefficient`.
- **Se sacrifica / deuda documentada:** este patrón acopla `Billing` a la estructura interna de los
  modelos de `Properties` (un rename de columna en `Properties` puede romper `Billing` sin que el
  compilador de PHP lo detecte hasta runtime, al no haber tipado de contrato explícito). Es deuda
  aceptable mientras el equipo sea pequeño y el monolito sea un solo repo — **se revisita** el día
  que `Properties` (o cualquier contexto leído así) se extraiga a un servicio separado, momento en el
  que la opción 2 o 3 de este mismo documento se vuelve obligatoria, no opcional.
- La regla de scope (mismo `condominium_id` de la request) es la única barrera real contra fuga de
  datos entre tenants por esta vía — cualquier bloque que lea cross-context debe declarar en su
  tarjeta qué modelo lee y bajo qué scope, para que el verificador lo confirme explícitamente (no es
  un criterio "obvio" que se dé por hecho).

## Alcance de la decisión

- [[../../features/COBRANZA/PANORAMA]] §3, §9.4 punto 4 — primer caso real, resuelve la postura
  tentativa que el panorama dejó pendiente.
- `api/API_ARCHITECTURE.md` — documentar esta regla como convención general de módulos cuando ese
  documento liste los bounded contexts (`Auth`, `Authorization`, `Mfa`, `Properties`, `Billing`).
- Cualquier feature futura con dependencia cross-context declarada en su §3 (`PORTERIA`,
  `PQRS_CUMPLIMIENTO`, `REPORTES`) hereda esta decisión sin tener que reabrir la discusión.
