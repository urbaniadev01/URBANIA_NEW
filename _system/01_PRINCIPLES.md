---
tipo: sistema
proyecto: shared
actualizado: 2026-07-05
---

# 01 — Principios

> Seis reglas. Ninguna es negociable. Si una tarea obliga a romper una, la tarea está mal
> planteada — se detiene y se re-plantea, no se rompe el principio.

## 1. Un dato, un dueño

Todo dato de estado (progreso, resultado de un bloque, contrato vigente) tiene **exactamente un
archivo dueño**. Cualquier otro documento que necesite mostrarlo **enlaza o transcribe una cita
efímera**, nunca lo copia como si fuera su propia fuente.

- El estado de un bloque vive en el frontmatter de su propia tarjeta (`features/<F>/blocks/*.md`).
- `_state/BOARD.md` es un **índice/rollup**, no una fuente — se regenera leyendo las tarjetas, nunca
  se edita un estado directamente ahí sin que la tarjeta ya lo diga.
- Si dos documentos alguna vez dicen cosas distintas sobre el mismo dato, es un bug de proceso que
  se corrige de inmediato, no una ambigüedad a interpretar.

**Por qué:** el vault anterior sincronizaba el mismo estado a mano en 6+ archivos (CHANGES_LOG,
SYSTEM_CONTRACT, dos FEATURES_INDEX, dos SESSION_MANIFEST, panoramas). Derivó, y obligó a resets de
baseline completos. Un solo dueño por dato hace ese drift estructuralmente imposible.

## 2. Diseño → Bloques → Ejecución

Un feature se diseña **una vez** (`PANORAMA.md`), se parte en **bloques pequeños** (`BLOCKS.md` +
tarjetas), y un agente ejecuta **un bloque por sesión**. Nunca se ejecuta un feature completo "de
corrido". Un bloque que resulta demasiado grande a mitad de sesión se **parte en bloques nuevos**,
no se termina "a medias" bajo el mismo bloque.

**Por qué:** es el pedido explícito del usuario y el mecanismo central contra errores de
interpretación — menos superficie por sesión, criterios de aceptación acotados, evidencia
verificable por bloque.

## 3. Contrato primero y con gates

Cuando un bloque involucra API + cliente (Web), el contrato de API se **congela**
(`_state/contracts/CONTRACT_LOCKS.md`) antes de que el bloque de cliente pueda empezar. El gate es
mecánico: un bloque de cliente no puede pasar a `ready` sin un lock vigente que referencie el bloque
de API en estado `done`.

**Por qué:** evita que cada capa construya contra una suposición distinta del contrato — la causa
raíz más cara de descubrir tarde.

## 4. "Done" = probado

Ningún bloque pasa a `done` sin evidencia pegada en su propia tarjeta: salida real de comandos de
CI, y para endpoints/pantallas, una verificación funcional real (no solo tipos/lint). El agente que
implementó no es quien certifica `done` — una verificación independiente (ver
[[06_AGENT_ROLES]]) lo confirma antes de que el estado cambie.

**Por qué:** el vault anterior marcó un feature "Completado/Sincronizado" con un endpoint de
registro efectivamente público, un gate de impersonation equivocado, tests faltantes y lint roto.
"Done" sin evidencia es una opinión, no un hecho.

## 5. Uniforme en todo

Mismo frontmatter, mismo vocabulario de estado, misma convención de numeración y misma forma de
archivo para API y Web (y para App cuando arranque). Ver [[02_CONVENTIONS]].

**Por qué:** el vault anterior tenía tres dialectos de estado (notas Dataview en API, tablas inline
en Web, texto libre en App). Sin una forma común no hay manera de que un agente generalice el
proceso entre proyectos, ni de auditar el vault con una sola pasada.

## 6. Read-set mínimo por agente

Cada rol de agente tiene una lista corta y determinista de qué lee antes de actuar (ver
[[06_AGENT_ROLES]]). Los documentos son monopropósito y cortos — si un documento intenta responder
dos preguntas distintas, se separa en dos.

**Por qué:** cuanto más ambiguo o disperso el contexto que un agente tiene que sintetizar, mayor la
probabilidad de que invente, omita o mezcle información de sesiones distintas.

## 7. Council para decisiones de alta criticidad

Para decisiones de diseño, verificación, arquitectura y release que implican alto riesgo o
complejidad, se aplica el protocolo de council multi-agente (ver
[[06_AGENT_ROLES#11. Design Council (design-council)]] y siguientes):

1. **Divergencia:** múltiples agentes especializados analizan el problema en paralelo, cada uno
   desde su lente.
2. **Peer Review anonimizado:** los outputs se anonimizan y cada agente evalúa el trabajo de los
   demás sin conocer su origen — elimina el sesgo de autopreferencia.
3. **Síntesis:** un chairman consolida todas las perspectivas en una decisión única y documentada.

**Cuándo aplica:** diseño de features complejos (`design-council`), verificación de bloques
críticos con `verificacion_critica: true` (`verify-council`), decisiones de arquitectura que
requieren ADR (`adr-council`), y gates pre-deploy (`release-council`).

**Cuándo NO aplica:** features simples, bloques de baja criticidad, tareas mecánicas. El council
tiene costo (múltiples agentes en paralelo) — se reserva para decisiones donde el costo del error
supera el costo de la deliberación.

**Por qué:** un solo agente, por bueno que sea, tiene puntos ciegos. El council aplica el mismo
principio que la verificación independiente (§4) pero desde el diseño y la revisión: la calidad
emerge del cruce de perspectivas diversas, no de la confianza en una sola.
