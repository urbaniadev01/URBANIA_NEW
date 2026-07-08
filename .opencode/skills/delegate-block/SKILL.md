---
name: delegate-block
description: Generar un prompt de delegación estructurado para que urbania y los orquestadores deleguen bloques con checklist explícito, alcance delimitado, y árbol de impacto — eliminando la improvisación en las instrucciones al builder.
---

## Cuándo se usa

Antes de cada delegación de un bloque a un builder (`api-build` o `web-build`). Lo invocan:
- `urbania` al delegar un bloque ready
- `api-orchestrator` al delegar a `api-build`
- `web-orchestrator` al delegar a `web-build`

## Pasos

### 1. Leer la tarjeta del bloque

Abrir la tarjeta en `features/<FEATURE>/blocks/<ID>.md` y extraer textualmente:
- Objetivo
- Alcance: Incluye (lista exacta)
- Alcance: No incluye (lista exacta)
- Criterios de aceptación (todos, incluidos los casos negativos)
- Definition of Done (copiado de la tarjeta)
- `depende_de` (del frontmatter)
- `proyectos` (del frontmatter)
- `contrato` (si aplica: `produce` o `consume`)

### 2. Verificar dependencias

Para cada entrada en `depende_de`:
- Si es un bloque: confirmar que está en estado `done` (leer la tarjeta del bloque dependencia)
- Si es un lock: confirmar que existe en `_state/contracts/CONTRACT_LOCKS.md`
- Si alguna dependencia no está satisfecha: reportarlo y detenerse

### 3. Construir el árbol de impacto

Consultar `_state/BOARD.md` para encontrar bloques que dependen del bloque actual (columna "Depende de").
Listar los que pasarían de `backlog` a `ready` si este bloque llega a `done`.

### 4. Determinar archivos esperados y prohibidos

Basado en el proyecto (`api` o `web`) y el alcance declarado:
- **Archivos esperados:** lista concreta de paths que el builder debería crear/modificar
- **Archivos prohibidos:** paths del otro proyecto que no debe tocar, y archivos de otros bounded contexts

### 5. Generar el prompt estructurado

Producir el siguiente bloque de texto exactamente con este formato — esto es lo que se pasa al builder:

```
## 🎯 BLOQUE: <ID>

**Proyecto(s):** <lista>
**Feature:** <nombre>

---

## 📋 Objetivo

[Copiado textual de la tarjeta]

---

## ✅ Alcance — INCLUYE

- [Ítem 1]
- [Ítem 2]

## ❌ Alcance — NO INCLUYE

- [Ítem 1]
- [Ítem 2]

---

## 🧪 Criterios de aceptación

### Camino feliz
- [ ] [Criterio 1]
- [ ] [Criterio 2]

### Casos negativos / seguridad
- [ ] [Criterio 3 — caso negativo]
- [ ] [Criterio 4 — caso de error]

---

## 📁 Archivos esperados

| Archivo | Acción |
|---|---|
| `code/api/app/Models/X.php` | Crear |
| `code/api/app/Http/Controllers/XController.php` | Crear |
| `code/api/database/migrations/...` | Crear |

## 🚫 Archivos PROHIBIDOS

- NO tocar `code/web/` (este es un bloque solo API)
- NO modificar modelos de otros bounded contexts
- NO alterar migraciones existentes

---

## 🔗 Dependencias

| Dependencia | Estado |
|---|---|
| `<DEP-1>` | ✅ done |
| `<LOCK-X>` (contract) | ✅ vigente en CONTRACT_LOCKS.md |

---

## 🌳 Árbol de impacto

Al completar este bloque:
- 🔓 `<BLOQUE-A>` pasaría de `backlog` → `ready`
- 🔓 `<BLOQUE-B>` pasaría de `backlog` → `ready`
- 🔒 Se creará el lock `<LOCK-NUEVO>` en `CONTRACT_LOCKS.md`

---

## ✅ Definition of Done

[Copiado textual de la tarjeta, adaptado al proyecto correspondiente]

---

## 🔧 Verificación final

Comando CI: `<composer ci | pnpm ci>`
```

### 6. Entregar el prompt

Entregar este bloque de texto al orquestador que invocó el skill. El orquestador lo usa como prompt
de delegación al builder.

## Regla dura

Si la tarjeta no tiene "No incluye" explícito, o si los criterios de aceptación no incluyen al menos
un caso negativo por cada acción de escritura/autorización, **detenerse y reportarlo** — no se rellena
el vacío. Un builder no puede implementar correctamente sin estos datos.
