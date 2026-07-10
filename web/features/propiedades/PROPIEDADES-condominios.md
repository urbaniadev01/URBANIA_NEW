---
tipo: referencia
proyecto: web
feature: PROPIEDADES
actualizado: 2026-07-09
---

# PROPIEDADES — Gestión de Condominios

**Bloque que la produce:** [[../../../features/PROPIEDADES/blocks/PROPIEDADES-B07-condominios-web]]
**Tipo:** Página (x2: lista + detalle)
**Rutas:** `/condominios`, `/condominios/{id}`

## Qué muestra

### Lista de condominios (`/condominios`)
- Grid de cards (1 columna en mobile, 2 en sm, 3 en lg), cada una con nombre, dirección, NIT y
  conteo de torres (cuando está disponible).
- Barra de búsqueda por nombre con filtrado local (case-insensitive).
- Botón "Nuevo condominio" que abre un Sheet con formulario (nombre, dirección, NIT).
- Estados: carga (spinner), vacío ("No hay condominios registrados"), sin resultados ("No se
  encontraron condominios con...").

### Detalle de condominio (`/condominios/{id}`)
- Breadcrumb: Condominios / {nombre}.
- Header con nombre, dirección y NIT.
- Dos tabs (shadcn/ui Tabs):
  - **Torres:** lista de torres con nombre, botones de editar/eliminar, y botón "Nueva torre".
    Estado vacío con mensaje guía.
  - **Configuración:** vista de solo lectura de datos + botón "Editar condominio" (abre Sheet).
    Zona de peligro con botón "Eliminar condominio" (deshabilitado si tiene torres) y diálogo de
    confirmación.
- Estados: carga, error 404 ("El condominio no existe o no tienes acceso").

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Crear condominio | Sheet → POST | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]] |
| Editar condominio | Sheet → PATCH | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]] |
| Eliminar condominio | Diálogo confirmación → DELETE | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]] |
| Crear torre | Sheet → POST | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]] |
| Editar torre | Sheet → PATCH | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]] |
| Eliminar torre | Diálogo confirmación → DELETE | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]] |
| Navegar a detalle | Click en card → `navigate(/condominios/{id})` | Ninguno (router) |
| Búsqueda | Input filtrando `items` en memoria | Ninguno (local) |

## Estados de la vista

### Lista de condominios
- **Carga:** Spinner centrado.
- **Vacío (sin datos):** Icono Building2 + "No hay condominios registrados." + botón "Crear primero".
- **Sin resultados (búsqueda):** "No se encontraron condominios con <término>." + botón "Limpiar
  búsqueda".
- **Error de API:** Toast de error (cada hook maneja su propio `onError`).

### Detalle de condominio
- **Carga:** Spinner centrado.
- **Error 404:** Icono AlertTriangle + "El condominio no existe o no tienes acceso." + botón "Volver
  a la lista".
- **Error 409 al eliminar:** El diálogo muestra el mensaje de error dentro del propio diálogo (no se
  cierra automáticamente).
- **Formularios inválidos:** Mensajes de validación en los campos correspondientes (Zod +
  react-hook-form).

## Permisos

- Solo usuarios con scope `organization` o `condominium` pueden acceder a estas pantallas (API
  retorna 403 para otros roles).
- Residentes (scope `unit`) no tienen acceso — la API rechaza con 403 `FORBIDDEN`.

## Componentes UI utilizados

- **Card** (shadcn/ui) — CondominioCard
- **Sheet** (shadcn/ui) — CondominioSheet, TorreSheet
- **Tabs** (shadcn/ui) — DetalleCondominioPage
- **Dialog** (shadcn/ui) — Diálogos de confirmación de eliminación
- **Input, Button, Label, Form** (shadcn/ui) — Formularios
- **Icons** (lucide-react) — Building2, MapPin, Hash, Plus, Pencil, Trash2, Loader2, Search, etc.

## Extensibilidad

Los tabs del DetalleCondominioPage están diseñados para ser extendidos:
- **B08** agregará el tab "Unidades".
- **B09** agregará el tab "Coeficientes".

La estructura actual (Tabs + TabsList + TabsTrigger + TabsContent) permite agregar nuevos tabs sin
modificar los existentes.
