# GUIA_DESARROLLO — Manual del desarrollador

> **Este documento es para personas, no para agentes.** Un agente no lee esto — su punto de entrada
> es `AGENTS.md` → `_system/00_START_HERE.md`. Esta guía es el manual de "cómo me paro yo, como
> desarrollador, frente a OpenCode en cada situación" — con pasos estrictos, no sugerencias.
>
> Si sos nuevo en el proyecto: leé este documento de punta a punta una vez, en orden. Después queda
> como referencia — cada Flujo (A–J) está pensado para consultarse solo cuando lo necesitás, no para
> memorizarse.

---

## 0. Qué es Urbania y qué es este vault

Urbania es un sistema de administración de conjuntos residenciales (API en Laravel + Web en React).
Este vault (`URBANIA_NEW`) es la documentación que gobierna cómo se construye — no contiene código.
El código vive en carpetas hermanas (`API/`, `WEB/`) que son repos git independientes.

**La idea central que tenés que entender antes de todo lo demás:** el trabajo no se organiza en
"features completas" ni en "sesiones libres" — se organiza en **bloques**: unidades de trabajo
pequeñas, con criterios de aceptación explícitos, que un agente ejecuta una a la vez. Vos no le decís
al agente "implementá login" — le decís "ejecutá el bloque `AUTH-B02`", y esa tarjeta ya contiene
exactamente qué hacer, qué no hacer, y cómo se sabe que quedó bien hecho.

Si en algún punto de esta guía una instrucción no tiene sentido, es más confiable el propio sistema
(`_system/`) que esta guía — reportá la discrepancia, no la resuelvas por tu cuenta.

---

## 1. Arrancar OpenCode en este vault

```bash
cd D:\Programacion\URBANIA_NEW
opencode
```

Esto carga `opencode.json` (instrucciones = `AGENTS.md`, MCPs: `urbania-db`, `codebase-memory`,
`playwright`) y arranca en el agente primario `urbania` — el router. Desde ahí:

- Para hablarle al router directamente: escribís tu pedido en lenguaje natural.
- Para dirigirte a un agente primario específico sin pasar por el router: `@nombre-agente tu mensaje`
  (ej. `@api-orchestrator ejecutá AUTH-B01`).
- Los agentes marcados como **subagente** en la tabla de §17 (`api-build`, `web-build`,
  `context-reader`) no se invocan directamente — los llama el orquestador correspondiente. Si le
  escribís a uno de ellos igual va a responder, pero estás saltándote el pipeline de verificación —
  no lo hagas salvo que sepas exactamente por qué.

---

## 2. El mapa del vault en 60 segundos

| Carpeta | Qué contiene | Cuándo la tocás vos directamente |
|---|---|---|
| `_system/` | La metodología — como se lee este manual, se lee una vez y se consulta poco | Casi nunca (ver Flujo H) |
| `_state/` | El tablero único (`BOARD.md`), el historial (`CHANGELOG.md`), los contratos congelados | Para consultar estado — nunca la editás a mano (ver §3) |
| `shared/` | Glosario, ADRs, el contrato entre API y Web | Al diseñar una feature que introduce vocabulario o decisiones de arquitectura nuevas |
| `features/<F>/` | El diseño de una feature (`PANORAMA.md`) y sus bloques ejecutables (`blocks/`) | Constantemente — es donde vive el trabajo real |
| `api/`, `web/` | Documentación técnica de cada proyecto (convenciones, no catálogo) | Rara vez a mano — normalmente la actualiza el agente como parte del DoD de un bloque |
| `app/` | Un solo archivo, diferido | No, hasta que decidas arrancarlo (Flujo J) |

---

## 3. Regla de oro que atraviesa toda esta guía

**Vos nunca editás `_state/BOARD.md` a mano para "marcar algo como hecho".** El estado real vive en
la tarjeta del bloque (`features/<F>/blocks/*.md`, campo `estado` del frontmatter). El `BOARD` es un
espejo — lo actualiza el agente, no vos. Si alguna vez ves que el `BOARD` dice algo distinto a lo que
dice la tarjeta, la tarjeta tiene razón y hay que corregir el `BOARD` — repórtalo, no lo "arregles"
cambiando la tarjeta para que coincida con el board.

---

## 4. Flujo A — Empezar una sesión de trabajo (lo que hacés casi siempre)

Este es tu punto de partida el 90% de las veces.

1. Abrí OpenCode en el vault (§1).
2. Decile al router: *"¿qué sigue?"* o simplemente no le des tarea — el router lee `_state/BOARD.md`
   solo.
3. El router te va a mostrar el primer bloque en `ready` y a qué orquestador lo va a delegar.
4. **Vos decidís si ese es el bloque que querés avanzar ahora.** Si sí, confirmás. Si preferís otro
   bloque específico, se lo indicás por su ID (`AUTH-B05`, etc.) — el router va directo a esa
   tarjeta.
5. A partir de acá, el pipeline corre solo (orquestador → build → verifier). Vos podés observar o
   alejarte — pero ver el Flujo D antes de confiar ciegamente en un `done`, sobre todo en tus
   primeras semanas con el sistema.

**Qué NO hacés en este flujo:** no le pedís al agente "segui avanzando con todo lo que puedas" ni
"terminá la feature completa" — eso rompe la regla de un bloque por sesión (`_system/03_LIFECYCLE.md`
§2). Un bloque, una sesión, siempre.

---

## 5. Flujo B — Consultar el estado del proyecto (sin ejecutar nada)

Para cuando solo querés saber dónde está todo, sin avanzar trabajo.

1. Abrí `_state/BOARD.md` directamente en el editor — no hace falta pasar por el agente para esto.
2. La tabla de "Features" te dice qué features existen y si su diseño está `approved` o todavía
   `draft`.
3. La tabla de "Bloques" por feature te dice el estado de cada uno (`backlog`, `ready`,
   `in_progress`, `verifying`, `blocked`, `done`).
4. Si querés el detalle de un bloque puntual (qué hace, qué falta), abrí directamente su tarjeta en
   `features/<F>/blocks/`.
5. Si querés el historial de lo ya entregado, `_state/CHANGELOG.md` (append-only — nunca se edita
   hacia atrás, así que es confiable como registro histórico).

No necesitás al agente para esto — es lectura directa de markdown, más rápido que preguntarle.

---

## 6. Flujo C — Diseñar y aprobar una feature nueva

Este es el flujo con más responsabilidad tuya, porque es el único gate de diseño de todo el sistema.

### C.1 — Pedir el borrador

1. Decile al router o a `@doc-agent`: *"quiero diseñar la feature X"*, con una descripción de qué
   problema resuelve.
2. `@doc-agent` va a copiar `_system/templates/FEATURE_PANORAMA.md` a
   `features/<NOMBRE>/PANORAMA.md` y completar §1–§6 con vos — es una conversación, no un documento
   que aparece solo. Prestá atención especial a:
   - **§4 (modelo de datos):** cada campo nuevo tiene que decir explícitamente si es **Valor** o
     **Referencia**. Si el agente deja algo ambiguo, no lo dejes pasar — es la fuente número uno de
     bugs de modelado más adelante.
   - **§3 (relación con otras features):** si la feature nueva depende de algo que todavía no existe
     (como pasó con Propiedades en `AUTH`), tiene que quedar anotado explícitamente qué se excluye
     por ahora.
3. El resultado queda con `estado_diseño: draft`. Ningún agente puede ir más allá de este punto sin
   vos.

### C.2 — Revisar antes de aprobar (checklist que hacés vos, a mano)

Antes de tocar el frontmatter, releé el panorama completo y confirmá:

- [ ] ¿El §1 (resumen) explica el problema en términos que entenderías sin haber estado en la
      conversación de diseño?
- [ ] ¿Cada campo de §4 tiene Valor/Referencia decidido, no pendiente?
- [ ] ¿El §6 (mapeo de acciones a endpoints) cubre todo lo que describiste en §1, ni de más ni de
      menos?
- [ ] ¿El §8 (checklist de aprobación del propio panorama) está todo marcado?
- [ ] ¿Hay algo en `shared/GLOSSARY.md` que este panorama debería usar y no está usando (términos
      duplicados con otro nombre)?

### C.3 — Aprobar

Si todo lo anterior está bien: editás vos mismo el frontmatter de `PANORAMA.md`,
`estado_diseño: draft` → `estado_diseño: approved`. Esto es una edición de un campo, la hacés a mano
en el editor — no hace falta pedírselo al agente (y si se lo pedís, tiene que quedar explícito en la
tarjeta que fue una delegación puntual tuya, no una decisión que tomó el agente solo).

### C.4 — Partir en bloques

Una vez `approved`, recién ahí volvés a `@doc-agent`: *"partí AUTH... digo, <NOMBRE>, en bloques"*.
Va a crear `BLOCKS.md` y las tarjetas siguiendo el mismo patrón que ves en `features/AUTH/` — usalo
como referencia mental de qué tan chico tiene que ser un bloque y qué tan explícitos los criterios
de aceptación (especialmente los casos negativos).

Revisá el plan de bloques antes de dar por bueno que empiecen a ejecutarse — mismo criterio que en
C.2: ¿cada bloque es ejecutable en una sesión? ¿el orden de dependencias tiene sentido?

---

## 7. Flujo D — Revisar evidencia y confiar (o no) en un cierre

Cuando un bloque llega a `estado: verifying`, el agente `@verifier` lo revisa de forma independiente
antes de pasarlo a `done`. Vos podés (y en las primeras semanas, deberías) revisar también.

1. Abrí la tarjeta del bloque en `verifying`.
2. Leé su sección "Evidencia" — tiene que haber salida real de comandos pegada (no un resumen tipo
   "todo pasó"), y para endpoints/UI, verificación funcional real.
3. Contrastá la evidencia contra la tabla de "Criterios de aceptación" de la misma tarjeta — ¿cada
   fila, incluidos los casos negativos, tiene su evidencia correspondiente?
4. Si algo te genera dudas, no dejes que `@verifier` lo cierre solo — pedile explícitamente que
   vuelva a correr el caso puntual, o hacelo vos mismo con los comandos de `api/API_AGENTS.md` §3 /
   `web/WEB_AGENTS.md` §3.
5. Una vez conforme, dejá que `@verifier` complete la transición a `done` (o hacelo notar si ya lo
   hizo y estás de acuerdo).

**Con el tiempo**, a medida que confiés en que el pipeline funciona, este flujo se vuelve una lectura
rápida en vez de una auditoría línea por línea — pero no te lo saltees por completo en los primeros
bloques reales que corras.

---

## 8. Flujo E — Un bloque toca API y Web (cross-project)

1. El bloque de API se ejecuta primero (Flujo D hasta `done`).
2. `@cross-project` congela el contrato en `_state/contracts/CONTRACT_LOCKS.md` — podés (y conviene)
   abrir ese archivo y leer la entrada nueva: método, ruta, request, response, errores. Es la forma
   exacta en la que Web va a construir, así que si algo ahí no es lo que esperabas, es el momento de
   corregirlo — **antes** de que el bloque de Web empiece.
3. Recién con el lock confirmado, el bloque de Web pasa a `ready`. El sistema no te va a dejar
   saltear este orden — si le pedís a `@web-orchestrator` que ejecute un bloque de cliente sin lock,
   tiene que negarse y decírtelo.
4. Cuando ambos lados llegan a `done`, `@cross-project` agrega la entrada de cierre en
   `_state/CHANGELOG.md`. Es un buen momento para leer esa entrada como registro de "esto ya se
   entregó de punta a punta".

**Si necesitás cambiar un contrato ya congelado** (por ejemplo, un bloque de Web nuevo necesita un
campo que el endpoint no devuelve): no se edita el lock existente. Se abre un bloque de API nuevo
(`_system/04_CROSS_PROJECT.md` §5) — pedíselo a `@doc-agent`, y tratalo como una feature/bloque
nuevo, no como una corrección menor.

---

## 9. Flujo F — Un bloque resultó más grande de lo esperado

Le puede pasar a cualquier bloque, no es un error de nadie. Si a mitad de sesión el agente de build
descubre que el alcance real es mayor al declarado en la tarjeta:

1. El agente detiene lo que excede el alcance original — no sigue "total ya que estoy".
2. Te avisa (o le pide a `@doc-agent`) que se creen tarjetas nuevas para lo que sobra, con el
   siguiente número libre del feature.
3. Vos revisás esas tarjetas nuevas con el mismo criterio del Flujo C.4 — no hace falta re-aprobar
   el panorama completo, solo confirmar que la partición tiene sentido.
4. El bloque original cierra con lo que sí entra en su alcance declarado, o queda `blocked` con una
   nota de en qué se partió si no quedó nada utilizable todavía.

---

## 10. Flujo G — Algo está bloqueado

1. Encontrás el bloque en `estado: blocked` en el `BOARD` o directamente en su tarjeta.
2. Leé su sección "Notas" — tiene que decir el motivo exacto (si no lo dice, es un bug de proceso:
   pedile al agente que lo complete antes de seguir).
3. Vos resolvés el impedimento (una decisión de producto, una dependencia externa, una pregunta que
   solo vos podés responder).
4. Una vez resuelto, el bloque vuelve a `ready` — normalmente lo hace el agente al confirmar que el
   impedimento se resolvió, pero si el impedimento era puramente tuyo (una decisión), sos vos quien
   se lo confirma explícitamente al agente para que haga el cambio.

---

## 11. Flujo H — Cambiar una convención o principio del sistema (`_system/`)

Esto es infrecuente y siempre lo iniciás vos, nunca un agente por su cuenta.

1. Identificá qué documento de `_system/` necesita cambiar y por qué (normalmente porque un patrón
   real que surgió en la práctica no encaja con la regla escrita).
2. Editá el documento vos mismo, o con un agente en modo asistido — pero la decisión de cambiar una
   regla del sistema es tuya, no delegable.
3. Si el cambio afecta cómo se ve una tarjeta de bloque o un panorama (por ejemplo, un campo nuevo
   obligatorio), actualizá también la plantilla correspondiente en `_system/templates/` — las
   tarjetas ya creadas no se migran retroactivamente salvo que decidas hacerlo explícitamente.
4. Un cambio de convención no es un `CAMBIO`/entrada de `CHANGELOG` (eso es para features
   cross-project) — es simplemente un commit de este vault con un mensaje claro de qué regla cambió
   y por qué.

---

## 12. Flujo I — Iniciar el track de App

Ver `app/APP_DEFERRED.md` para el criterio de arranque completo. En resumen: vos decidís cuándo, no
antes de que AUTH esté `done` y haya al menos un feature de negocio adicional completo en API+Web.
Cuando decidas arrancarlo, es un Flujo C (diseño de feature) aplicado a la documentación técnica de
App misma — no un checkbox que se activa solo.

---

## 13. Checklist rápido — qué es tuyo y qué es delegable

| Decisión | ¿Quién la toma? |
|---|---|
| Qué feature se diseña a continuación | Vos |
| Contenido del panorama de una feature (§1–§6) | Conversación vos + agente, redactado por el agente |
| Aprobar un panorama (`draft` → `approved`) | **Vos, siempre** |
| Cómo se parte una feature en bloques | Agente propone, vos revisás antes de que empiecen a ejecutarse |
| Ejecutar un bloque `ready` | Delegable por completo al pipeline de agentes |
| Mover una tarjeta a `verifying` | Agente de build, automático |
| Mover una tarjeta a `done` | Agente `@verifier`, automático — pero **revisado por vos** mientras generás confianza en el sistema (Flujo D) |
| Congelar/cambiar un contrato de API | Agente `@cross-project`, mecánico según reglas fijas — vos revisás el contenido del lock antes de que Web lo consuma |
| Resolver un bloqueo | Vos (la causa raíz casi siempre es una decisión de producto) |
| Cambiar una regla de `_system/` | **Vos, siempre** |
| Decidir cuándo arranca App | **Vos, siempre** |

---

## 14. Comandos de referencia

**API** (ver `api/API_AGENTS.md` §3 para el detalle completo):
```bash
composer ci        # lint + stan + test — lo que corre @api-build antes de reportar terminado
composer test
```

**Web** (ver `web/WEB_AGENTS.md` §3):
```bash
pnpm ci             # type-check + lint + test + build
pnpm test
```

No hace falta que los corras vos mismo salvo que quieras confirmar evidencia de forma independiente
(Flujo D) — el agente ya los corre como parte de su Definition of Done.

---

## 15. Errores comunes al empezar con este sistema

| Lo que se te va a ocurrir pedir | Por qué no | Qué pedir en su lugar |
|---|---|---|
| "Segui avanzando con todo lo que puedas" | Rompe la regla de un bloque por sesión | "Ejecutá el siguiente bloque `ready`" |
| "Implementá la feature X completa" | No existe una unidad "feature completa" ejecutable — solo bloques | Pedí el plan de bloques primero (Flujo C.4), después ejecutalos uno a uno |
| Editar `_state/BOARD.md` a mano para marcar algo `done` | El `BOARD` es un espejo, no la fuente — se desincroniza | Editá la tarjeta del bloque, o dejá que `@verifier` lo haga |
| Aprobar un panorama sin leerlo completo | Es el único gate real de diseño del sistema — si falla acá, falla en cascada | Usá el checklist de §C.2 siempre, aunque parezca repetitivo |
| Pedirle a `@api-build`/`@web-build` directamente sin pasar por el orquestador | Te salteás el gate de verificación independiente | Hablále al router o al orquestador, dejá que él delegue |

---

## 16. Glosario de roles de agente (versión humana de `_system/06_AGENT_ROLES.md`)

| Agente | Qué hace | ¿Le hablás vos directamente? |
|---|---|---|
| `urbania` | Router — lee el `BOARD` y delega | Sí, es tu punto de entrada normal |
| `api-orchestrator` / `web-orchestrator` | Coordinan la ejecución de un bloque de su proyecto, nunca implementan | Sí, si querés saltear el router |
| `api-build` / `web-build` | Implementan un único bloque | No directamente — los invoca el orquestador |
| `context-reader` | Lee la tarjeta asignada y resume, sin opinar | No — uso interno del pipeline |
| `verifier` | Verificación independiente; único que mueve una tarjeta a `done` | Podés pedirle directamente que revise un bloque en `verifying` |
| `cross-project` | Gestiona los contratos congelados y el gate API→Web | Podés pedirle directamente que te muestre el estado de un lock |
| `doc-agent` | Crea features nuevas, bloques, y audita coherencia del vault | Sí, es con quien hablás en el Flujo C |

---

## 17. Si esta guía y el sistema (`_system/`) no coinciden

Gana `_system/`. Esta guía es una capa de conveniencia sobre esos documentos, no una fuente
independiente — si encontrás una diferencia, es esta guía la que está desactualizada. Corregila (o
pedile a un agente que la corrija) en el mismo momento en que la notes, no la dejes para después.
