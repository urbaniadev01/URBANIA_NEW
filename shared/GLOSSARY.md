---
tipo: referencia
proyecto: shared
actualizado: 2026-07-03
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
| **Contacto (Contact)** | Persona registrada en el sistema. Todo usuario autenticado tiene un contacto asociado (`contacts.user_id` único). Tabla `contacts`. | API, Web |
| **Ocupante (Occupant)** | Vínculo entre un contacto y una unidad, con un tipo de ocupante específico. Tabla `property_occupants`. | API, Web |
| **Tipo de ocupante** | Catálogo configurable de roles dentro de una unidad (propietario, residente, inquilino). Tabla `occupant_types`. | API, Web |
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

## Cómo se agregan términos nuevos

Al crear el `PANORAMA.md` de una feature nueva, si introduce vocabulario de dominio, se agrega aquí
como parte del checklist de aprobación de esa feature (ver
`_system/templates/FEATURE_PANORAMA.md` §8). No se documenta vocabulario de una feature que todavía
no tiene panorama aprobado.
