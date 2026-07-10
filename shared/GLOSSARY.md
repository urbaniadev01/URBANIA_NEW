---
tipo: referencia
proyecto: shared
actualizado: 2026-07-09
---

# GLOSSARY — Vocabulario de dominio compartido

> Confirmar el término aquí antes de nombrarlo distinto en código o en un documento nuevo. Si el
> término no existe todavía, se propone y se agrega. Si el mismo concepto aparece con nombres
> distintos entre API y Web, se corrige el nombrado — nunca se agrega un sinónimo nuevo.

## Términos fundacionales (arquitectura — ADR-001)

| Término | Significado | Dónde se usa |
|---|---|---|
| **Actor** | Concepto de autoría: el usuario (`user_id`) que ejecuta una acción o crea un registro (`created_by`, quién aprobó, quién cambió un estado). | API, Web |
| **Party** | Concepto de pertenencia: la persona (`contact_id`) vinculada a una unidad (`property_id` vía `property_occupants`) para efectos de derechos u obligaciones (dueño de cuenta, residente, radicante de una solicitud). | API, Web |
| **Organización (Organization)** | Tenant raíz del sistema SaaS. Agrupa usuarios, condominios y contactos. Tabla `organizations`. | API, Web |
| **Condominio (Condominium)** | Conjunto residencial / propiedad horizontal administrado dentro de una organización. | API, Web |
| **Propiedad horizontal** | Término legal colombiano equivalente a "condominio" — el edificio/conjunto administrado. | API, Web |
| **Unidad (Property)** | Apartamento, casa, local o parqueadero individual dentro de un condominio. Tabla `properties`. | API, Web |
| **Contacto (Contact)** | Persona registrada en el sistema, con o sin cuenta de login. Todo usuario autenticado tiene un contacto asociado (`contacts.user_id`, único cuando no es `NULL`) — pero un contacto puede existir sin usuario (propietario ausente, registrado por obligación de la Ley 675). Tabla `contacts`. | API, Web |
| **Ocupante (Occupant)** | Vínculo entre un contacto y una unidad, con un tipo de ocupante específico. Tabla `property_occupants` (feature DIRECTORIO). | API, Web |
| **Tipo de ocupante** | Catálogo configurable de roles dentro de una unidad (propietario, residente, arrendatario, familiar), mismo patrón sistema/tenant que otros catálogos. Tabla `occupant_types`. | API, Web |
| **Permiso (Permission)** | Acción permitida sobre un recurso, expresada `recurso.accion` (ej. `pagos.ver`). Catálogo fijo, tabla `permissions`. | API |
| **Rol (Role)** | Conjunto de permisos asignable a un usuario. Rol de sistema o personalizado por organización. Tabla `roles`. | API |
| **Alcance / Scope** | Nivel y objeto sobre el que aplica una asignación de rol: `organization`, `condominium`, `tower` o `unit`. | API |
| **Asignación de rol (Role assignment)** | Vínculo entre un usuario, un rol y un scope, con vigencia opcional. Tabla `role_assignments`. | API |
| **Residente** | Persona que habita o administra una unidad. Se modela como `contact` + `property_occupants` de tipo `residente` — nunca como atributo de `users`. | API, Web |

## Términos de autenticación (feature AUTH)

| Término | Significado | Dónde se usa |
|---|---|---|
| **Invitación** | Token único, de vigencia acotada, enviado a una persona para que se registre y quede vinculada a una unidad. Tabla `invitations`. | API, Web |
| **Registro por invitación** | El único camino de alta de un usuario nuevo: el registro exige un `invitation_token` válido, vigente y no consumido — nunca un registro abierto sin invitación. | API, Web |
| **Activación** | Proceso mediante el cual un usuario en estado `pending_activation` establece su contraseña y transiciona a `active`. | API, Web |
| **MFA** | Autenticación multifactor (TOTP + códigos de respaldo). | API |
| `trace_id` | Identificador único de una request/respuesta del API, usado para correlacionar logs entre cliente y servidor. | API, Web |
| **Refresh token rotation** | Cada uso de un refresh token lo invalida y emite uno nuevo; la reutilización de uno ya invalidado se trata como señal de robo de sesión. | API |

## Términos de propiedades (feature PROPIEDADES)

| Término | Significado | Dónde se usa |
|---|---|---|
| **Torre (Tower)** | Subdivisión física opcional dentro de un condominio (un edificio o bloque). No todo condominio tiene torres — un conjunto de casas o edificio único no las necesita. Tabla `towers`. | API, Web |
| **Coeficiente (de copropiedad)** | Fracción (0-1) que representa la participación de una unidad en las áreas y obligaciones comunes del condominio. Tiene temporalidad (`vigente_desde`/`vigente_hasta`) porque puede recalcularse. Tabla `property_coefficients`. | API, Web |
| **Catálogo de sistema vs. personalizado** | Patrón aplicado a `property_types` y `property_statuses`: registros con `organization_id = NULL` son catálogo base compartido (inmutable por tenants); registros con `organization_id` propio son personalizados por esa organización. | API, Web |
| **Árbol de condominio (tree)** | Vista jerárquica de conveniencia (`condominium → towers → properties` con conteos) para navegación en Web, sin ser una entidad propia. | API, Web |

## Términos de directorio (feature DIRECTORIO)

| Término | Significado | Dónde se usa |
|---|---|---|
| **Contacto sin login** | Un `contact` con `user_id = NULL` — persona registrada en el directorio sin cuenta de acceso al sistema (ej. propietario ausente, familiar). Se crea vía `POST /contacts`, nunca vía el flujo de registro de AUTH (ese siempre crea el par usuario+contacto juntos). | API, Web |
| **`es_principal`** | Flag en `property_occupants` que marca, entre varios ocupantes del mismo tipo en la misma unidad, a cuál dirigir por defecto una factura o notificación. Único por `(property_id, occupant_type_id)` — no reemplaza un vínculo de propiedad legal. | API, Web |

## Términos de dashboard (feature DASHBOARD)

| Término | Significado | Dónde se usa |
|---|---|---|
| **Dashboard** | Pantalla principal post-login que compone widgets de múltiples features según los permisos del usuario. No es un módulo de negocio — es una superficie de composición. El nombre en UI es "Inicio". | Web |

## Términos de cobranza (feature COBRANZA)

| Término | Significado | Dónde se usa |
|---|---|---|
| **Concepto de cobro (Charge concept)** | Ítem configurable que puede facturarse a una unidad (administración, fondo de imprevistos, multa, extraordinaria), con un método de cálculo asociado (coeficiente, fijo, por área, manual). No compartido entre condominios. Tabla `charge_concepts`. | API, Web |
| **Periodo de facturación (Billing period)** | Ciclo mensual de cobro de un condominio (`anio`+`mes`), con ciclo de vida `abierto → facturado → cerrado`. Tabla `billing_periods`. | API, Web |
| **Corrida de facturación (Billing run)** | Ejecución asíncrona que prorratea los conceptos de cobro entre las unidades activas de un condominio según su coeficiente vigente, generando las cuentas de cobro del periodo. A lo sumo una `completada` por periodo. Tabla `billing_runs`. | API, Web |
| **Cuenta de cobro (Invoice)** | Factura emitida a una unidad para un periodo de facturación, compuesta por ítems (`invoice_items`). Su `estado` (`pendiente`/`parcial`/`pagada`/`vencida`) se deriva en lectura a partir de `saldo` y `fecha_vencimiento`, nunca se almacena. Tabla `invoices`. | API, Web |
| **Recibo de pago (Payment receipt)** | Registro manual de un pago o abono hecho por un contacto sobre una o varias unidades. Tabla `payment_receipts`. | API, Web |
| **Aplicación de pago (Payment allocation)** | Distribución de un recibo de pago sobre una cuenta de cobro específica; la suma de las aplicaciones de un mismo recibo debe igualar exactamente su valor (Fase 1 no modela saldo a favor). Tabla `payment_allocations`. | API, Web |
| **Paz y salvo (Peace certificate)** | Certificado que acredita que una unidad no tiene saldo pendiente, emitido de forma síncrona y revocable. Tabla `peace_certificates`. | API, Web |
| **Fondo de imprevistos** | Concepto de cobro (`charge_concepts.tipo = fondo_imprevistos`) para reservas de contingencia del condominio — no es una cuenta bancaria separada en Fase 1, solo un ítem de facturación. | API, Web |

## Cómo se agregan términos nuevos

Al crear el `PANORAMA.md` de una feature nueva, si introduce vocabulario de dominio, se agrega aquí
como parte del checklist de aprobación de esa feature (ver
`_system/templates/FEATURE_PANORAMA.md` §8). No se documenta vocabulario de una feature que todavía
no tiene panorama aprobado.
