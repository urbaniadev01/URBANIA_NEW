---
tipo: pantalla
feature: PROPIEDADES
bloque: PROPIEDADES-B09
actualizado: 2026-07-09
---

# PROPIEDADES-coeficientes — Tab "Coeficientes"

> Pantalla del tab "Coeficientes" en `DetalleCondominioPage`. Consume LOCK-PROPIEDADES-04
> y LOCK-PROPIEDADES-03.

## 1. Ruta

`/condominios/{id}?tab=coeficientes`

## 2. Propósito

Permitir al administrador asignar, editar y visualizar coeficientes de todas las unidades
de un condominio en una tabla editable en lote, con validación visual de la suma de
copropiedad en tiempo real.

## 3. Componentes

| Componente | Archivo | Rol |
|---|---|---|
| `CoeficientesTab` | `components/CoeficientesTab.tsx` | Tab completo: tabla, controles, lógica de edición y guardado |
| `SumaBar` | `components/SumaBar.tsx` | Barra de suma de copropiedad con indicador verde/ámbar |

## 4. Hooks de API (TanStack Query)

| Hook | Endpoint | Lock |
|---|---|---|
| `usePropertyCoefficientsQuery(propertyId)` | `GET /properties/{id}/coefficients` | LOCK-PROPIEDADES-04 |
| `useBatchPropertyCoefficientsQueries(propertyIds)` | `GET /properties/{id}/coefficients` × N | LOCK-PROPIEDADES-04 |
| `useCondominioTreeQuery(condominiumId)` | `GET /condominiums/{id}/tree` | LOCK-PROPIEDADES-04 |
| `useUpdateCoefficientsMutation(condominiumId)` | `PATCH /condominiums/{id}/coefficients` | LOCK-PROPIEDADES-04 |

Para la lista de unidades se reutiliza `usePropertiesInfiniteQuery` (LOCK-PROPIEDADES-03).

## 5. Estados y flujo

### Carga inicial
1. Al montar el tab, se dispara `usePropertiesInfiniteQuery` para obtener todas las unidades.
2. Se auto-fetchean todas las páginas (hasNextPage → fetchNextPage).
3. Con todos los property IDs, se ejecuta `useBatchPropertyCoefficientsQueries` en paralelo.
4. Se construyen las filas: una por cada (unidad × tipo de coeficiente).
5. Se muestran la tabla y la barra de suma.

### Edición inline
- Cada fila tiene un input numérico para el valor del coeficiente.
- El estado editado se gestiona en `editedValues` (Record local, no TanStack Query).
- `useMemo` recalcula la suma de copropiedad en cada cambio → barra se actualiza en tiempo real.
- Las filas modificadas se resaltan con fondo ámbar.

### Guardado masivo
- Solo se envían filas modificadas (diff contra `originalValor`).
- El PATCH es atómico: el servidor aplica todo o nada.
- Éxito: se limpia `editedValues`, se invalidan queries de coeficientes.
- Error: toast con mensaje apropiado, los datos locales NO se pierden.

### Filtro por tipo
- Selector dropdown con los 4 tipos de coeficiente.
- Filtra la tabla localmente (todos los datos ya están cargados).
- La barra de suma siempre muestra copropiedad, independientemente del filtro.

### Toggle historial
- Botón "Ver historial" que muestra/oculta columnas `vigente_desde` y `vigente_hasta`.
- El coeficiente vigente se resalta con fondo verde y checkmark.
- No se recargan datos del servidor.

### Indicador de suma (SumaBar)
- **Verde:** suma de copropiedad = 1.0 (100%) → "Suma: 100.0% ✓"
- **Ámbar:** suma ≠ 1.0 → "Suma actual: 85.0% — se requiere 100%"
- Se actualiza en tiempo real con `useMemo` (derived state).

## 6. Reglas de negocio implementadas

| Regla | Implementación |
|---|---|
| R-05: Coeficiente vigente único | Servidor cierra el anterior al crear nuevo. Frontend refleja vigencia en toggle historial. |
| R-06: Suma copropiedad = 1.0 | SumaBar muestra indicador visual. No bloquea guardado (warning del servidor). |
| R-06-bis: Set cerrado de tipos | Selector dropdown usa `COEFFICIENT_TYPES` constante (copropiedad, parqueadero, deposito, mantenimiento). |

## 7. Errores manejados

| Código | Mensaje toast |
|---|---|
| `COEFFICIENT_OUT_OF_RANGE` (422) | "Uno o más valores están fuera del rango permitido (0–1)." |
| `COEFFICIENT_INVALID_TYPE` (422) | "Tipo de coeficiente inválido." |
| `PROPERTY_NOT_IN_CONDOMINIUM` (422) | "Una o más unidades no pertenecen a este condominio." |
| `PROPERTY_NOT_FOUND` (404) | "Una o más unidades no existen." |
| `CONDOMINIUM_NOT_FOUND` (404) | "El condominio no existe." |
| `COEFFICIENT_SUM_MISMATCH` (200 warning) | Toast warning con el porcentaje actual. |

## 8. Tipos

Definidos en `features/propiedades/types/index.ts`:
- `CoefficientType` — unión de tipos válidos
- `CoefficientItem` — entidad completa del API
- `CoefficientRow` — fila compuesta para la tabla
- `UpdateCoefficientsRequest` / `UpdateCoefficientsResponse`
- `CondominioTreeResponse`
- `COEFFICIENT_ERROR_CODES`

## 9. Dependencias

- shadcn/ui: `Button`, `Table`, `Select`
- TanStack Query: `useQueries` (para batch de coeficientes)
- Zustand: access token (vía apiClient)
- sonner: toasts
