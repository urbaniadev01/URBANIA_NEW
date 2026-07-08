---
tipo: sistema
proyecto: shared
actualizado: 2026-07-05
---

# 02 — Convenciones

> Fuente única de: frontmatter, nombres de archivo, vocabulario de estado y reglas de numeración.
> Ningún otro documento redefine esto — si algo aquí no alcanza para un caso real, se corrige aquí
> primero.

## 1. Frontmatter — obligatorio en todo `.md` del vault

```yaml
---
tipo: sistema | referencia | contrato | feature | bloque | adr | estado
proyecto: shared | api | web | app
estado: <solo si tipo: bloque — ver vocabulario abajo>
verificacion_critica: true | false  <opcional, solo si tipo: bloque. Indica que el bloque requiere verificación por verify-council antes de done>
actualizado: YYYY-MM-DD
---
```

- `tipo` clasifica QUÉ ES el documento (metodología, doc técnico de referencia, contrato congelado,
  panorama de feature, tarjeta de bloque, decisión de arquitectura, o tablero de estado vivo).
- `proyecto` clasifica A QUIÉN pertenece. `shared` = ningún proyecto es dueño exclusivo.
- `estado` solo aparece en tarjetas de bloque (`tipo: bloque`) — es el único campo de estado
  ejecutable del vault (ver [[03_LIFECYCLE]] para el vocabulario y sus transiciones).
- `verificacion_critica` solo aparece en tarjetas de bloque (`tipo: bloque`). Si es `true`, el
  verificador independiente debe invocar al `verify-council` (ver [[06_AGENT_ROLES#12]]) antes de
  decidir `done`. Si no está presente, se asume `false`. Ver [[05_DEFINITION_OF_DONE#6]] para
  criterios de uso.
- `actualizado` se toca cada vez que el contenido cambia de forma material (no en cada typo).

## 2. Nombres de archivo

| Qué | Patrón | Ejemplo |
|---|---|---|
| Doc de metodología | `NN_NOMBRE.md` (numerado, orden de lectura) | `03_LIFECYCLE.md` |
| Plantilla | `_system/templates/NOMBRE.md` | `BLOCK.md` |
| Panorama de feature | `features/<FEATURE>/PANORAMA.md` | `features/AUTH/PANORAMA.md` |
| Plan de bloques de un feature | `features/<FEATURE>/BLOCKS.md` | `features/AUTH/BLOCKS.md` |
| Tarjeta de bloque | `features/<FEATURE>/blocks/<FEATURE>-B<NN>-<slug>.md` | `features/AUTH/blocks/AUTH-B01-login-basico.md` |
| Endpoint (detalle API) | `api/endpoints/<FEATURE>.md` | `api/endpoints/AUTH.md` |
| Pantalla Web | `web/features/<feature-slug>/<FEATURE>-<pantalla-slug>.md` | `web/features/auth/AUTH-login.md` |
| ADR cross-project | `shared/adr/ADR-<NNN>-<slug>.md` | `shared/adr/ADR-001-actor-party.md` |
| ADR local de proyecto | `<proyecto>/adr/ADR-<PROY>-<NNN>-<slug>.md` | `api/adr/ADR-API-001-uuid-v7.md` |

`<FEATURE>` siempre en MAYÚSCULAS, una sola palabra o `SNAKE_CASE` corto (`AUTH`, `PROPIEDADES`,
`REGISTRO_RESIDENTES`). `<slug>` siempre en minúsculas con guiones.

## 3. Numeración — regla dura: append-only, nunca se reasigna

- **Bloques**: `<FEATURE>-B<NN>`, dos dígitos, secuencial dentro del feature, empezando en `01`. Un
  bloque cancelado o descartado **no libera su número** — queda marcado `estado: cancelado` en su
  tarjeta y el siguiente bloque nuevo usa el próximo número libre.
- **ADRs**: un único contador por ámbito (`shared/adr/` o cada `<proyecto>/adr/`), tres dígitos,
  nunca se reutiliza.
- **CHANGELOG**: cada entrada de `_state/CHANGELOG.md` es un evento append-only con timestamp — no
  se edita retroactivamente, solo se agrega.

No existen "sesiones" numeradas por proyecto como eje organizador — el eje es el bloque. Si hace
falta referirse a cuándo ocurrió el trabajo, se usa la fecha real, no un contador paralelo.

## 4. Vocabulario de estado — el único que existe en el vault

Aplica solo a tarjetas de bloque (`tipo: bloque`). No existen sinónimos ("Completado", "Sincronizado",
"✅") en ningún otro documento — todo estado se expresa con estas seis palabras exactas:

| Estado | Significado |
|---|---|
| `backlog` | Existe en `BLOCKS.md` pero su tarjeta no está lista para ejecutarse (dependencias no cumplidas o criterios aún sin definir) |
| `ready` | Tarjeta completa, dependencias satisfechas, contrato (si aplica) congelado — un agente puede tomarlo |
| `in_progress` | Un agente lo está ejecutando ahora mismo |
| `verifying` | Implementación reportada por el agente, pendiente de verificación independiente |
| `blocked` | No puede avanzar; la tarjeta registra el motivo exacto en su sección "Notas" |
| `done` | DoD cumplido con evidencia pegada Y verificación independiente confirmada |

Transición válida únicamente: `backlog → ready → in_progress → verifying → done`, con salida a
`blocked` desde cualquier estado activo y retorno a `ready` una vez resuelto el bloqueo. Ningún
agente escribe `done` directamente — solo el rol verificador (ver [[06_AGENT_ROLES]]) hace esa
transición final.

## 5. Wikilinks

Se usa `[[nombre-de-archivo-sin-extensión]]` de estilo Obsidian. Un enlace a una sección específica
usa `[[archivo#Sección]]`. Ningún documento **repite** contenido que puede enlazar — si la tentación
es copiar una tabla o una regla de otro doc, se enlaza esa sección en su lugar.

## 6. Idioma

Todo el vault en español (paridad con el vault humano de Urbania). `CLAUDE.md` es la única
excepción — bilingüe, porque Claude Code opera aquí en modo auditoría/asesor y su propio CLAUDE.md
histórico es en inglés.
