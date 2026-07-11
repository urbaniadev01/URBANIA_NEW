# GUÍA FUNCIONAL — Qué se puede hacer hoy en Urbania

> **Este documento es para personas que usan o prueban la aplicación, no para agentes ni
> desarrolladores.** Describe únicamente lo que está **implementado y funcional** en `code/web` +
> `code/api` a la fecha de esta guía — no el roadmap ni el diseño aprobado todavía sin construir.
> Para eso último ver los `PANORAMA.md` de cada feature en `features/`. Fuente de verdad del estado
> real de cada pieza: `_state/BOARD.md`.
>
> **Última actualización:** 2026-07-11.

---

## 0. Cómo saber si esto sigue vigente

Esta guía puede quedar desactualizada apenas se cierre un bloque nuevo. Antes de confiar en una
sección puntual, contrastá contra `_state/BOARD.md`: cualquier bloque en **`done`** es funcional tal
como se describe acá; **`verifying`** significa "funciona, pendiente de una revisión final antes de
darlo por definitivo" (se nota explícitamente abajo dónde aplica); **`ready`/`backlog`** significa que
todavía no existe en la aplicación, aunque el diseño ya esté aprobado.

---

## 1. Qué es Urbania

Un sistema de administración de conjuntos residenciales (propiedad horizontal). Hoy cubre identidad y
sesión (AUTH), la estructura física de los condominios (PROPIEDADES) y el directorio de personas
(DIRECTORIO), todo con una pantalla principal (DASHBOARD) que las conecta. Cobranza, comunicaciones,
portería y portal de residente están diseñados pero **todavía no implementados** (ver §7).

---

## 2. Entrar a la aplicación

### 2.1 Iniciar sesión — `/login`

Correo + contraseña. Si la cuenta tiene MFA habilitado, redirige a `/mfa/verify` (código TOTP de 6
dígitos o un código de recuperación de un solo uso). La sesión se mantiene al refrescar la página
(recupera el access token contra la cookie `refresh_token`, httpOnly).

### 2.2 Registrarse — `/register/:token`

Solo por invitación — no existe alta abierta. El link de invitación (con token) lo genera un admin
(vía API; todavía no hay pantalla para gestionar invitaciones desde la Web). El registro completa
nombre + contraseña y crea la cuenta.

### 2.3 Recuperar contraseña

`/forgot-password` (pedir el link de reseteo por correo) → `/reset-password` (definir la nueva
contraseña con el token recibido).

### 2.4 MFA (autenticación de dos factores)

- `/mfa/enroll` (requiere sesión iniciada): activar MFA — genera un QR para una app TOTP
  (Google Authenticator, Authy, etc.) y una lista de códigos de recuperación de un solo uso.
- `/mfa/verify`: paso obligatorio en el login si la cuenta ya tiene MFA activo.

### 2.5 Cerrar sesión y perfil

El menú de usuario (header, arriba a la derecha) permite cerrar sesión y llegar a `/perfil` — ver
datos del propio contacto (nombre, correo, teléfono) y editarlos.

### 2.6 Modo oscuro / claro

Toggle en el header, persistido en el navegador. Por defecto sigue la preferencia del sistema
operativo.

---

## 3. Panel principal — `/` o `/dashboard`

Landing page tras el login. Muestra información y accesos según el rol del usuario — nadie ve más de
lo que su rol le permite (ver §6).

| Widget | Qué muestra | Quién lo ve |
|---|---|---|
| **Mis Condominios** | Lista de hasta 5 condominios con conteo de unidades. Al hacer clic en uno, queda "activo" y alimenta los dos widgets siguientes. Link "Ver todos" → `/condominios`. | Requiere permiso de ver condominios |
| **Unidades recientes** | Hasta 5 unidades del condominio activo. Requiere haber seleccionado un condominio primero. | Requiere permiso de ver unidades |
| **Estructura** | Árbol colapsable: condominio → torres (con conteo de unidades) → unidades sin torre. | Requiere permiso de ver condominios |
| **Accesos directos** | Botones a Condominios, Unidades, Coeficientes, Directorio (Cobranza aparece pero todavía no lleva a ninguna pantalla funcional). Cada botón se oculta solo si falta el permiso — no se muestra deshabilitado. | Todo usuario autenticado (cada botón individualmente según permiso) |
| **Directorio** | Placeholder "Próximamente" — todavía sin datos reales conectados en el dashboard. | Solo roles de staff (admin/manager) |
| **Cuotas pendientes** | Placeholder "En desarrollo" (Cobranza no existe todavía). | Solo roles de staff |

Cada widget tiene sus propios cuatro estados (cargando / vacío / error con botón "Reintentar" / datos
reales) — si uno falla, no rompe a los demás.

El header además tiene: búsqueda rápida de pantallas (`Cmd/Ctrl+K`), un botón de personalización visual
(bordes, color de acento, escala de UI) y KPIs mini (condominios/unidades/torres en el alcance del
usuario).

---

## 4. Propiedades — la estructura física de un condominio

### 4.1 Catálogos globales

- **Tipos de propiedad** — `/catalogos/tipos-propiedad`: CRUD de tipos (ej. Apartamento, Local,
  Parqueadero). Incluye tipos "de sistema" (no editables/eliminables) y tipos personalizados por
  organización.
- **Estados de propiedad** — `/catalogos/estados-propiedad`: mismo patrón, para estados (ej.
  Ocupado, Disponible, En remodelación).

### 4.2 Condominios — `/condominios`

Listado con búsqueda, crear/editar/eliminar (soft delete — no se puede eliminar uno con torres o
unidades activas). Cada condominio abre a su página de detalle.

### 4.3 Detalle de condominio — `/condominios/{id}`

Página con breadcrumb y pestañas:

- **Torres:** listado, crear/editar/eliminar. No se puede eliminar una torre con unidades activas.
- **Unidades** (tab dentro de esta misma página): listado filtrable por torre/tipo/estado/búsqueda,
  crear/editar/eliminar. Cada unidad tiene código, piso, área (m², dato sensible — solo visible en el
  detalle de la unidad, nunca en el listado), tipo y estado.
- **Coeficientes** (tab): gestión masiva de los coeficientes de copropiedad/parqueadero/depósito/
  mantenimiento de todas las unidades del condominio en una sola operación atómica. Avisa (sin
  bloquear el guardado) si la suma de coeficientes de copropiedad no da 100%.
- **Configuración:** datos generales editables del condominio (nombre, dirección, NIT).

---

## 5. Directorio — personas del condominio

> Los tres bloques de API están `done`. Las 3 pantallas Web (§5.1–§5.3) están en estado
> **`verifying`**: funcionan de punta a punta, pero todavía falta la verificación visual final antes
> de considerarlas definitivas — no es un impedimento para probarlas.

### 5.1 Tipos de ocupante — `/catalogos/tipos-ocupante`

CRUD de tipos (ej. Propietario, Arrendatario, Familiar). Mismo patrón sistema/personalizado que los
catálogos de PROPIEDADES.

### 5.2 Contactos — `/directorio/contactos`

Listado con búsqueda de todas las personas de la organización — con o sin cuenta de acceso al
sistema (un contacto puede existir solo como registro, ej. un propietario ausente). Crear, editar,
eliminar (no se puede eliminar un contacto con ocupaciones activas). Los datos de contacto
(correo/teléfono) de terceros solo son visibles para staff con permiso de gestión — un residente no
ve los datos de contacto de sus vecinos.

### 5.3 Asignación de ocupantes

Vincula un contacto a una unidad con un tipo de ocupante (ej. "Juan Pérez es Propietario de la unidad
101") y marca si es el ocupante principal de ese tipo en esa unidad (a quién dirigir por defecto una
notificación). Un mismo contacto puede tener varios tipos en varias unidades.

### 5.4 Mi perfil — `/perfil`

Cualquier usuario autenticado puede ver y editar su propio contacto sin necesitar permisos
administrativos.

---

## 6. Roles y permisos (RBAC)

El acceso a cada pantalla y cada acción está controlado por permisos reales (no una columna de rol de
texto libre) con alcance (`scope`): organización completa, un condominio específico, o una torre
específica — un administrador de un solo condominio no ve ni gestiona los demás condominios de la
misma organización, aunque compartan tenant.

**Credenciales de desarrollo disponibles** (entorno local, no producción):

| Usuario | Contraseña | Notas |
|---|---|---|
| `admin@urbania.test` | `Admin123!` | Rol admin, scope organización completa — ve el grupo "Administración" del menú. |
| `test+mfa@urbania.test` | `Secret1pass` | MFA pre-habilitado (TOTP `JBSWY3DPEHPK3PXP`, códigos de recuperación `RECV0-ERY01`…`RECV0-ERY08`). Sin rol RBAC asignado. |

Los roles `manager` y `resident` existen sembrados en el sistema pero sin usuario de prueba asignado.

---

## 7. Qué NO está disponible todavía

Diseñado y aprobado, pero sin una sola pantalla ni endpoint funcional en la aplicación:

- **Cobranza** — conceptos de cobro, periodos de facturación, cuentas de cobro, pagos, paz y salvo.
- **Comunicaciones** — anuncios/avisos del condominio.
- **Portería** — control de visitantes y correspondencia.
- **Portal del residente** — widgets de saldo y avisos para el rol residente en el Dashboard.

Nada de esto tiene ruta en la Web ni endpoint activo en la API — aparecen únicamente como
placeholders ("Próximamente" / "En desarrollo") donde ya se diseñó su lugar en el Dashboard.

---

## 8. Limitaciones conocidas del entorno actual

- No hay verificación visual automatizada (Playwright) corriendo en este entorno — los cambios de UI
  se validan manualmente. Esto no afecta la funcionalidad, solo el proceso de control de calidad.
- El logo actual es un placeholder (JPG con fondo blanco, no un PNG/SVG recortado) — pendiente de un
  activo de marca definitivo.
