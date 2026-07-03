---
tipo: referencia
proyecto: api
feature: <FEATURE>
actualizado: YYYY-MM-DD
---

> Plantilla — copiar a `api/endpoints/<FEATURE>.md`. Este documento es el detalle de request/response
> de los endpoints de un feature; `api/API_CONTRACT.md` solo indexa (nunca duplica el detalle) y
> `features/<FEATURE>/PANORAMA.md` §6 solo mapea acción→endpoint a alto nivel.

# Endpoints: <FEATURE>

## <MÉTODO> /<ruta>

**Bloque que lo produce:** [[../../features/<FEATURE>/blocks/<FEATURE>-B<NN>-...]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-<FEATURE>-<NN>]]

### Request

```json
{
  "campo": "tipo — obligatorio/opcional — regla"
}
```

### Response — éxito (`200`/`201`)

```json
{
  "campo": "tipo"
}
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 4xx | `<CODIGO_ERROR>` | <condición exacta> |

> Cada error listado aquí debe corresponder a un caso negativo de la tabla de criterios de
> aceptación del bloque que lo produjo — no se documentan errores "por si acaso" que ningún
> criterio de aceptación ejercita.

### Autorización

Qué permiso/rol exacto se requiere, y qué gate de autorización lo verifica (nombre de la clase/
policy real, no una descripción vaga).
