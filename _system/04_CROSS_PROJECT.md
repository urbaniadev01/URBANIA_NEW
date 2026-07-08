---
tipo: sistema
proyecto: shared
actualizado: 2026-07-05
---

# 04 — Protocolo Cross-Project

> Reemplaza el flujo de texto libre "Propuesto → En progreso → Sincronizado" por una máquina de
> estados con gates mecánicos y evidencia obligatoria. Este documento es la única fuente del
> protocolo — un agente que necesite entenderlo lo lee aquí, nunca de memoria ni de un resumen de
> otro documento.

## 1. Cuándo aplica

Un bloque es cross-project si su tarjeta declara `proyectos: [api, web]` (o incluye `app` cuando ese
track arranque). Si hay duda razonable sobre si algo cruza proyectos, se trata como cross-project —
es más barato tratar un cambio local como cross-project que descubrir tarde uno que no se registró
(este criterio se conserva del vault anterior porque es correcto).

## 2. La máquina de estados

```
DESIGNED ──────> API_BUILDING ──────> API_VERIFIED ──────> CLIENTS_BUILDING ──────> CLIENTS_VERIFIED ──────> SHIPPED
(panorama         (bloque de API        (contrato del bloque    (bloque(s) de cliente      (verificación           (release-council
 approved,         tomado por un         congelado en           tomados; solo pueden        independiente del       emite GO;
 bloques           agente, estado        CONTRACT_LOCKS.md;     entrar en `ready`           lado cliente            CHANGELOG.md
 planificados)     de API en             estado de API en       si existe un lock             confirma DoD)          recibe la
                   `in_progress`)        `done`)                 vigente para este bloque)                             entrada final)
```

Cada flecha exige una condición mecánica verificable — ninguna transición es una declaración de
intención en texto libre.

| Transición | Condición que la habilita | Dónde se verifica |
|---|---|---|
| `DESIGNED → API_BUILDING` | El bloque de API pasa a `estado: in_progress` en su propia tarjeta | La tarjeta del bloque de API |
| `API_BUILDING → API_VERIFIED` | El bloque de API llega a `estado: done` (DoD + verificación independiente cumplidos, ver [[05_DEFINITION_OF_DONE]]) | La tarjeta del bloque de API |
| `API_VERIFIED → CLIENTS_BUILDING` | Existe una entrada vigente en `_state/contracts/CONTRACT_LOCKS.md` que referencia el bloque de API, Y el bloque de cliente pasa a `in_progress` | `_state/contracts/CONTRACT_LOCKS.md` + tarjeta del bloque de cliente |
| `CLIENTS_BUILDING → CLIENTS_VERIFIED` | El/los bloque(s) de cliente llegan a `estado: done` | Tarjeta(s) del bloque de cliente |
| `CLIENTS_VERIFIED → SHIPPED` | El `release-council` ([[06_AGENT_ROLES#14]]) emite veredicto GO y se agrega la entrada de cierre en `_state/CHANGELOG.md` | `_state/CHANGELOG.md` |

## 3. Regla dura: un bloque de cliente no puede empezar sin contrato congelado

Un bloque de Web con `proyectos: [web]` que depende de un endpoint declara esa dependencia en su
campo `depende_de` apuntando al ID del bloque de API correspondiente. El orquestador de Web **no
puede** mover ese bloque a `ready` si:
- el bloque de API del que depende no está `done`, o
- no existe una entrada correspondiente en `CONTRACT_LOCKS.md`.

Esto es mecánico, no una revisión manual: si cualquiera de las dos condiciones falta, el bloque
permanece en `backlog` sin importar qué tan urgente parezca.

## 4. Formato de una entrada en `CONTRACT_LOCKS.md`

```markdown
## LOCK-<FEATURE>-<NN> — <método> <ruta>
- Origen: [[features/<FEATURE>/blocks/<FEATURE>-B<NN>-...]]
- Congelado: YYYY-MM-DD
- Request: <esquema resumido o enlace a api/endpoints/<FEATURE>.md#sección>
- Response (éxito): <esquema resumido>
- Errores: <lista de códigos con su significado>
- Consumido por: [[features/<FEATURE>/blocks/<FEATURE>-B<NN>-cliente>]] (se agrega cuando el bloque
  de cliente lo toma)
```

Un lock es inmutable mientras esté "Consumido por" activo. Congelar no significa "para siempre" —
significa "para los bloques que ya lo referencian ahora mismo".

## 5. Cómo se cambia un contrato ya congelado

Cambiar la forma de un contrato con locks activos **no es una edición** — es un cambio cross-project
nuevo y explícito:

1. Se abre un bloque nuevo de API (`<FEATURE>-B<NN+1>`) cuyo objetivo es la migración del contrato.
2. Ese bloque nuevo pasa por la misma máquina de estados desde `DESIGNED`.
3. El lock anterior se marca `Reemplazado por: LOCK-<FEATURE>-<NN+1>` — no se borra (append-only,
   ver [[02_CONVENTIONS]] §3).
4. Los bloques de cliente que consumían el lock viejo quedan con una nota de migración pendiente;
   no se asume que se actualizan solos.

**Por qué:** el vault anterior permitía que un contrato cambiara silenciosamente mientras el otro
lado ya había construido contra la versión anterior — la causa raíz de integración rota que este
protocolo existe para eliminar.

## 6. Registro append-only en `_state/CHANGELOG.md`

Cuando un cross-project llega a `SHIPPED`, se agrega una entrada — nunca se edita una entrada
pasada. Formato en [[../_state/CHANGELOG]]. Esto reemplaza los "resets de baseline" del vault
anterior: si el historial crece mucho, se resume en un documento aparte, pero el log en sí nunca se
trunca ni se reescribe.
