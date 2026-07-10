---
tipo: referencia
proyecto: web
feature: PROPIEDADES
actualizado: 2026-07-09
---

# PROPIEDADES — Pantalla de Unidades

**Bloque que la produce:** [[../../../features/PROPIEDADES/blocks/PROPIEDADES-B08-pantalla-unidades]]
**Tipo:** Tab dentro de Página
**Ruta:** `/condominios/{id}?tab=unidades`

## Qué muestra

Tab "Unidades" dentro del detalle de condominio. Muestra una tabla paginada con las unidades del condominio: código, torre (nombre resuelto), tipo, estado, y piso. No incluye `area_m2` en la tabla (R-10: el listado no la expone). Incluye filtros combinables y acciones individuales y en lote.

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Listar unidades | Carga inicial y al cambiar filtros | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]] GET |
| Crear unidad | Click en "Nueva unidad" → Sheet → Guardar | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]] POST |
| Editar unidad | Click en ✏️ en fila → Sheet → Guardar | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]] PATCH |
| Eliminar unidad | Click en 🗑️ en fila → Confirmar | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]] DELETE |
| Cambiar estado en lote | Seleccionar filas → "Cambiar estado" → Elegir estado → Aplicar | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]] PATCH × N |
| Eliminar en lote | Seleccionar filas → "Eliminar seleccionadas" → Confirmar | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]] DELETE × N |
| Cargar dropdowns (torres) | Al montar el tab | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]] GET |
| Cargar dropdowns (tipos/estados) | Al montar el tab | [[../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]] GET |

## Estados de la vista

- **Carga:** Spinner centrado mientras se obtienen las unidades.
- **Vacío:** Ilustración con mensaje "No hay unidades registradas" y botón "Crear primera unidad".
- **Error de carga:** Mensaje "Error al cargar las unidades" con ícono de advertencia. El tab no se rompe — el resto de la UI (header, tabs) permanece navegable.
- **Error 422 (código duplicado):** Toast "Ya existe una unidad con ese código en este condominio".
- **Error 422 (torre mismatch):** Toast "La torre seleccionada no pertenece a este condominio".
- **Error 409 (ocupantes):** Toast "No se puede eliminar: la unidad tiene ocupantes activos".
- **Error 403:** Toast "No tienes permiso para [acción]".
- **Error de red:** Toast genérico "Error al [acción] la unidad.".

## Permisos

Solo usuarios con scope `organization`, `condominium`, o `tower` pueden acceder a este tab. Residentes (scope `unit`) reciben 403 del API — la UI muestra el toast de error correspondiente.
