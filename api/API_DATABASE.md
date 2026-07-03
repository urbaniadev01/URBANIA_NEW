---
tipo: referencia
proyecto: api
actualizado: 2026-07-03
---

# API_DATABASE — Esquema real implementado

> Las **convenciones** de esquema (tipos de clave, naming, soft delete) y las **tablas
> fundacionales** conceptuales viven en [[../shared/DATA_MODEL]] — no se repiten aquí. Este
> documento es el **esquema físico real**, tabla por tabla, tal como quedó implementado — se llena a
> medida que cada bloque que crea una tabla llega a `done` (parte del DoD de API, ver
> [[../_system/05_DEFINITION_OF_DONE]] §2). Mientras un bloque no esté `done`, su tabla no aparece
> aquí — este documento nunca describe una tabla que no existe todavía en el código.

## Estado

_Vacío — el vault arranca en punto 0. La primera tabla (`organizations`, `users`, `contacts`, o la
que `AUTH-B01`/`AUTH-B02` requieran primero) se agrega aquí en cuanto ese bloque complete su
Definition of Done._

## Formato por tabla (usar al agregar la primera)

```markdown
### `<tabla>`

| Columna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | uuid | PK | UUID v7 |
| ... | ... | ... | ... |

- Índices: ...
- Bloque que la creó: [[../features/<FEATURE>/blocks/<FEATURE>-B<NN>-...]]
```
