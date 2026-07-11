---
tipo: feature
proyecto: shared
feature: <FEATURE>
estado_diseño: draft
actualizado: YYYY-MM-DD
---

> Plantilla — copiar a `features/<FEATURE>/PANORAMA.md`. No editar este archivo directamente. El
> gate `estado_diseño: draft → approved` lo cambia un humano (o una delegación explícita registrada
> aquí mismo) — ver `_system/03_LIFECYCLE.md` §3. Mientras esté en `draft`, ningún agente crea
> `BLOCKS.md` ni escribe código para este feature.

# Feature: <NOMBRE>

## 1. Resumen y motivación

¿Qué problema resuelve? ¿Por qué ahora? (2–3 líneas)

## 2. Capas afectadas

- [ ] API (origen del contrato)
- [ ] Web
- [ ] App — si aplica, ver `app/APP_DEFERRED.md` para el criterio de arranque; si App aún no ha
      arrancado, se documenta la intención aquí pero no se planean bloques de App todavía.

## 3. Relación con otras features

- Depende de: [[../<OTRA>/PANORAMA]] (¿por qué?)
- Es consumido por: [[../<OTRA>/PANORAMA]] (¿por qué?)

## 4. Modelo de datos (si el feature crea o toca entidades)

Para cada entidad nueva: nombre de tabla, y para cada campo, si es **Valor** (columna inline) o
**Referencia** (FK a un catálogo/tabla propia). Ver `shared/DATA_MODEL.md` para las convenciones de
esquema (UUID v7, soft delete, naming de FKs) que cualquier entidad nueva debe respetar.

| Entidad | Nueva/Existente | Campo | Valor/Referencia | Notas |
|---|---|---|---|---|
| `<tabla>` | Nueva | `<campo>` | Valor / Referencia (`→ tabla.id`) | ... |

## 5. Reglas de negocio globales

Reglas que aplican a todos los proyectos por igual. Una regla que solo aplica a un proyecto va en la
documentación técnica de ese proyecto, no aquí.

- <Regla 1>

## 6. Mapeo de acciones a endpoints (alto nivel)

El detalle de request/response vive en `api/endpoints/<FEATURE>.md` — aquí solo el mapeo, como
puente entre el diseño y el contrato.

| Acción del usuario | Verbo | Endpoint |
|---|---|---|
| <acción> | GET/POST/PATCH/DELETE | `/<recurso>` |

## 7. UI/UX (obligatoria si §2 marca Web)

> Si §2 no marca Web, escribir `N/A — feature sin capa Web` y saltar al §8.

**Tier:** Estándar | Novedosa — declarar cuál aplica y por qué en una línea. "Novedosa" es un
dashboard, una visualización de datos, o un layout que no es CRUD/formulario estándar; todo lo
demás es "Estándar". Ver `web/WEB_VISUAL_STANDARDS.md` §2.

### 7.1 Pantallas afectadas

| Pantalla | Tipo (Página/Modal/Drawer/Sheet/Inline) | Ruta | Nueva/Existente |
|---|---|---|---|
| <pantalla> | ... | `/...` | ... |

### 7.2 Componentes de librería usados

Lista de componentes de `src/components/ui/` que cubren estas pantallas. Si falta un componente en
la librería instalada, se declara aquí como dependencia (ver `web/WEB_VISUAL_STANDARDS.md` §1).

### 7.3 Estados de la vista

| Pantalla | Loading | Vacío | Error | Éxito |
|---|---|---|---|---|
| <pantalla> | ... | ... | ... | ... |

### 7.4 Navegación

Cómo se llega a estas pantallas (sidebar, ruta directa, entry point desde otra pantalla).

> --- Solo si Tier = Novedosa — completar 7.5 y 7.6; si Tier = Estándar, omitir ambos. ---

### 7.5 Wireframe (ASCII)

### 7.6 Responsive

| Breakpoint | Columnas/adaptaciones |
|---|---|

## 8. Plan de bloques

Una vez `estado_diseño: approved`, el detalle de bloques vive en `BLOCKS.md` (mismo directorio que
este panorama) — este panorama no enumera bloques individuales, solo referencia que existen.

## 9. Checklist de aprobación (gate)

- [ ] §4 (modelo de datos): cada campo nuevo declara Valor o Referencia explícitamente
- [ ] §6 (mapeo de acciones a endpoints) cubre toda acción visible al usuario descrita en §1/§5
- [ ] §7 (UI/UX) completa si §2 marca Web: pantallas, componentes y estados declarados; si
      Tier = Novedosa, wireframe y responsive también completos
- [ ] Nombres de campos y entidades consistentes con `shared/GLOSSARY.md`
- [ ] No hay una feature existente en `features/` que ya cubra esto (revisar `_state/BOARD.md`)

> Al marcar todos los ítems, este documento puede pasar a `estado_diseño: approved` y recién ahí se
> crea `BLOCKS.md`.
