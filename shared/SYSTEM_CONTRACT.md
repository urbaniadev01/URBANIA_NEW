---
tipo: contrato
proyecto: shared
actualizado: 2026-07-03
---

# SYSTEM_CONTRACT — El contrato entre API y Web

> Índice, no fuente de verdad. Si hay discrepancia con el documento técnico del proyecto o con un
> lock en `_state/contracts/CONTRACT_LOCKS.md`, gana ese documento y este índice se corrige (ver
> [[../_system/01_PRINCIPLES#1. Un dato, un dueño]]).

## 1. Interfaces compartidas

| Interfaz | Fuente de verdad real | Quién la define | Quién la consume |
|---|---|---|---|
| Contrato REST (endpoints, formato de error, paginación, versionado) | `api/API_CONTRACT.md` (convenciones) + `api/endpoints/<FEATURE>.md` (detalle) + `_state/contracts/CONTRACT_LOCKS.md` (lo congelado) | API | Web |
| Códigos de error y su significado de negocio | `api/API_CONTRACT.md` §"Códigos de error" | API | Web — nunca redefine un código con otro significado |
| Requisitos de seguridad del lado cliente (storage de tokens, rotación) | `api/API_ARCHITECTURE.md` §Seguridad (define el requisito) → `web/WEB_ARCHITECTURE.md` (cómo Web lo implementa) | API define, Web implementa | Web |
| Vocabulario de dominio | [[GLOSSARY]] | Compartido | API, Web |
| Identidad visual / design tokens | Pendiente de decisión — cada proyecto define la suya hasta que se registre una feature cross-project que lo formalice | A decidir | Web |

## 2. Política de cambios sobre este contrato

Ningún proyecto modifica unilateralmente una fila de §1 sin pasar por
[[../_system/04_CROSS_PROJECT]]. Un contrato de endpoint concreto solo es real cuando existe su lock
en `_state/contracts/CONTRACT_LOCKS.md` — un endpoint mencionado en un panorama de feature en
`draft` no es un compromiso todavía.

## 3. Decisiones de arquitectura cross-project

| ADR | Título | Estado |
|---|---|---|
| [[adr/ADR-001-actor-party]] | Fundación multi-tenant + RBAC + actor/party canónico | Aceptada |

## 4. Regla de actor y party

> Aprobada en [[adr/ADR-001-actor-party]]. Separar estrictamente la identidad de cuenta del rol de
> pertenencia a una unidad.

| Concepto | Representación técnica | Cuándo se usa |
|---|---|---|
| **Actor** | `user_id` (tabla `users`) | Autoría: quién ejecutó una acción (`created_by`, quién aprobó, quién cambió un estado) |
| **Party** | `contact_id` (tabla `contacts`) + `property_id` vía `property_occupants` | Pertenencia: dueño de cuenta, residente, radicante de una solicitud |

**Invariantes:**
1. Todo `user` activo tiene al menos un `contact` asociado (`contacts.user_id` único y obligatorio).
2. La asociación persona↔unidad pasa obligatoriamente por `property_occupants`. No existe columna de
   texto libre alternativa.
3. Un `contact` puede existir sin `user`; un `user` no puede existir sin `contact`.

**Implicación directa para AUTH:** el endpoint de registro (`AUTH-B01`) crea `user` + `contact` en la
misma transacción — nunca un `user` huérfano de `contact`.

## 5. Documentos relacionados

| Documento | Propósito |
|---|---|
| [[../_system/04_CROSS_PROJECT]] | Cómo se propone y sincroniza un cambio a este contrato |
| [[../_state/CHANGELOG]] | Registro de cambios cross-project entregados |
| [[GLOSSARY]] | Vocabulario referenciado en §1 |
| [[DATA_MODEL]] | Esquema físico que implementa la regla de §4 |
