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
El código vive bajo `code/` (`code/api/`, `code/web/`), repos git independientes — no como
carpetas hermanas en mayúsculas, para no colisionar con `api/`/`web/` de este vault en Windows.

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
  (ej. `@api-orchestrator ejecutá AUTH-B01` o `@urbania levantá el entorno`).
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
| `app/` | Un solo archivo, diferido | No, hasta que decidas arrancarlo (Flujo I) |

---

## 3. Regla de oro que atraviesa toda esta guía

**Vos nunca editás `_state/BOARD.md` a mano para "marcar algo como hecho".** El estado real vive en
la tarjeta del bloque (`features/<F>/blocks/*.md`, campo `estado` del frontmatter). El `BOARD` es un
espejo — lo actualiza el agente, no vos. Si alguna vez ves que el `BOARD` dice algo distinto a lo que
dice la tarjeta, la tarjeta tiene razón y hay que corregir el `BOARD` — repórtalo, no lo "arregles"
cambiando la tarjeta para que coincida con el board.

---

## 3-bis. Los dos primeros bloques reales — arrancar de cero

Antes de que exista una sola línea de `AUTH`, hacen falta los proyectos mismos. `code/api/` y
`code/web/` no existen todavía — los crean `API_BOOTSTRAP-B01` y `WEB_BOOTSTRAP-B01`
(`features/API_BOOTSTRAP/`, `features/WEB_BOOTSTRAP/`), que son los únicos dos bloques en `ready`
del vault hoy. Son independientes entre sí — podés ejecutarlos en cualquier orden o en paralelo.
Todo lo demás (`AUTH-B01` en adelante) depende, directa o indirectamente, de que estos dos terminen.

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

El proceso depende de la complejidad del feature. `urbania` decide la ruta — vos solo describís lo
que necesitás.

#### Features simples (1 endpoint, 1 pantalla, reglas directas) → `@doc-agent`

1. Decile a `urbania`: *"quiero diseñar la feature X"*, con una descripción de qué problema
   resuelve. `urbania` evalúa la complejidad y delega a `@doc-agent`.
2. `@doc-agent` va a copiar `_system/templates/FEATURE_PANORAMA.md` a
   `features/<NOMBRE>/PANORAMA.md` y completar §1–§6 **con vos** — es una conversación, no un
   documento que aparece solo. Prestá atención especial a:
   - **§4 (modelo de datos):** cada campo nuevo tiene que decir explícitamente si es **Valor** o
     **Referencia**. Si el agente deja algo ambiguo, no lo dejes pasar — es la fuente número uno de
     bugs de modelado más adelante.
   - **§3 (relación con otras features):** si la feature nueva depende de algo que todavía no existe
     (como pasó con Propiedades en `AUTH`), tiene que quedar anotado explícitamente qué se excluye
     por ahora.
3. El resultado queda con `estado_diseño: draft`.

#### Features complejos (múltiples endpoints/pantallas, reglas intrincadas) → `design-council`

1. Decile a `urbania`: *"quiero diseñar la feature X"*. `urbania` reconoce la complejidad y delega
   a `design-council`.
2. `design-council` opera de forma **autónoma** — no interactúa con vos durante el diseño. Usa un
   protocolo de 3 fases:
   - **Fase 1 — Divergencia:** 3 subagentes (`design-architect`, `design-ux`, `design-security`)
     generan diseños independientes en paralelo.
   - **Fase 2 — Peer Review:** los diseños se anonimizan (A/B/C) y cada subagente rankea y critica
     los 3, incluido el propio.
   - **Fase 3 — Síntesis:** `design-council` produce un `PANORAMA.md` unificado con §1–§6 más una
     sección extra **"Veredicto del Design Council"** que documenta puntos de acuerdo, divergencias
     y la recomendación final.
3. El resultado queda con `estado_diseño: draft`.

**En ambas rutas:** el panorama queda en `draft` — ningún agente puede ir más allá sin tu
aprobación. Ni `@doc-agent` ni `design-council` se invocan directamente (son `hidden: true`) —
siempre pasan por `urbania`.

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

### C.3-bis — Si la feature tiene pantallas de Web

Urbania Web es un panel administrativo (ver
[[web/adr/ADR-WEB-001-libreria-componentes]]) — por defecto **no** hace falta preparar un mockup,
imagen o HTML de referencia antes de aprobar el panorama. Los componentes ya están resueltos
(shadcn/ui, instalados en `WEB_BOOTSTRAP-B01`); la tabla de "Criterios de aceptación" de cada bloque
de pantalla es la especificación completa. Reservá una referencia visual solo para una pantalla
genuinamente novedosa (un dashboard con datos, un layout que no es CRUD/formulario estándar) — y en
ese caso, va como una nota/imagen dentro del propio `WEB_SCREEN.md` de esa pantalla cuando se cree,
no como un documento de diseño aparte ni como un paso previo obligatorio.

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


## 13. Flujo J — Auditoría de integridad del vault

> El vault tiene un mecanismo de autocorrección: un agente `auditor` que barre todas las reglas del
> sistema (BOARD vs tarjetas, estados, frontmatter, wikilinks, contract locks, evidencia, gates
> cross-project) y reporta inconsistencias en una tabla plana, sin narrativa. Las inconsistencias
> **no se encolan** — se corrigen en el momento y se re-audita hasta que no quede ninguna.

### Cuándo se gatilla

| Trigger | Quién lo activa |
|---|---|
| Cada **3 bloques `done`** desde la última auditoría | `urbania` automáticamente |
| Cuando el último bloque de un feature pasa a `done` | `urbania` automáticamente |
| Antes de que un bloque de cliente pase a `ready` en un cross-project | `urbania` (mini-auditoría de contract locks) |
| Cuando vos decís "auditá el vault" | Vos, directamente |

Los triggers 1 y 2 usan un contador que `urbania` lleva internamente. El umbral de 3 bloques es
configurable.

### Qué revisa

1. **Drift BOARD vs. tarjetas** — que `_state/BOARD.md` refleje fielmente el frontmatter de cada
   tarjeta de bloque (es la regla #1 del sistema).
2. **Vocabulario de estado** — que ningún bloque use un estado fuera de los 6 permitidos.
3. **Frontmatter y estructura** — que todo `.md` tenga frontmatter válido y nombres correctos.
4. **Wikilinks** — que no haya enlaces rotos.
5. **Contract locks** — que cada lock esté respaldado por un bloque productor `done`, que los
   consumidores estén registrados, y que ningún bloque web esté `ready` sin su lock vigente.
6. **Evidencia en bloques `done`** — que la evidencia pegada sea real (output de comandos), no
   afirmaciones.
7. **Gate cross-project** — que las transiciones de la máquina de estados se respetaron.
8. **Correspondencia ADRs** — que las decisiones de arquitectura están reflejadas en los docs
   técnicos.

### Cómo se lee el reporte y qué hacés

El `auditor` produce una tabla plana como esta:

```
┌─────┬──────────────────────┬──────────┬─────────────────────┐
│  #  │ Hallazgo             │ Severidad│ Ref                 │
├─────┼──────────────────────┼──────────┼─────────────────────┤
│  1  │ BOARD dice backlog,  │ ❌       │ _state/BOARD.md L:42│
│     │ tarjeta dice ready   │          │                     │
│  2  │ Wikilink roto        │ ⚠️       │ PANORAMA.md L:15    │
└─────┴──────────────────────┴──────────┴─────────────────────┘
Resumen: 2 hallazgos (1 ❌, 1 ⚠️) · severidad: CRÍTICO
```

- ❌ = hay que arreglarlo antes de seguir. No se avanza ni un bloque más hasta corregirlo.
- ⚠️ = aviso, no bloquea, pero conviene revisarlo pronto.
- Si hay ❌, se arreglan en el momento y se re-corre la auditoría hasta que no quede ninguno.
- No existe "lo arreglamos después" para los ❌.

### Cómo pedir una auditoría

```bash
# En la conversación con OpenCode:
"auditá el vault"
# o:
"@auditor check contract-locks"
```

### Qué NO es el auditor

No es un reemplazo del `@verifier` (que revisa un bloque puntual cuando está en `verifying`).
El `@verifier` es micro (un bloque); el `auditor` es macro (todo el vault). El `@verifier` sí
escribe (cambia estados de tarjetas); el `auditor` nunca escribe nada.

---

## 14. Checklist rápido — qué es tuyo y qué es delegable

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

## 15. Comandos de referencia

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

## 16. Errores comunes al empezar con este sistema

| Lo que se te va a ocurrir pedir | Por qué no | Qué pedir en su lugar |
|---|---|---|
| "Segui avanzando con todo lo que puedas" | Rompe la regla de un bloque por sesión | "Ejecutá el siguiente bloque `ready`" |
| "Implementá la feature X completa" | No existe una unidad "feature completa" ejecutable — solo bloques | Pedí el plan de bloques primero (Flujo C.4), después ejecutalos uno a uno |
| Editar `_state/BOARD.md` a mano para marcar algo `done` | El `BOARD` es un espejo, no la fuente — se desincroniza | Editá la tarjeta del bloque, o dejá que `@verifier` lo haga |
| Aprobar un panorama sin leerlo completo | Es el único gate real de diseño del sistema — si falla acá, falla en cascada | Usá el checklist de §C.2 siempre, aunque parezca repetitivo |
| Pedirle a `@api-build`/`@web-build` directamente sin pasar por el orquestador | Te salteás el gate de verificación independiente | Hablále al router o al orquestador, dejá que él delegue |

---

## 17. Glosario de roles de agente (versión humana de `_system/06_AGENT_ROLES.md`)

> Actualizado 2026-07-08 — refleja los 30 agentes activos en `.opencode/agents/` (31 archivos,
> 1 obsoleto: `urbania-ops`). Si un agente aparece aquí pero no en esos archivos (o viceversa),
> esta tabla está desactualizada — reportalo.

### 🧭 Entrada

| Agente | Qué hace | ¿Le hablás vos directamente? |
|---|---|---|
| `urbania` | **Router + infraestructura.** Lee el `BOARD`, decide a qué orquestador delegar cada bloque, y ejecuta operaciones de entorno directamente (Docker, Composer, PNPM, migraciones). No implementa features. | Sí — es tu punto de entrada normal. También le podés pedir tareas de infraestructura directamente ("levantá el entorno"). |
| ~~`urbania-ops`~~ | **OBSOLETO** — fusionado en `urbania` el 2026-07-04. Sus responsabilidades de infraestructura ahora las ejecuta `urbania` directamente. Permanece desactivado (`disable: true`). | No — no lo uses. Usá `urbania` en su lugar. |

### 🎯 Coordinación

| Agente | Qué hace | ¿Le hablás vos directamente? |
|---|---|---|
| `api-orchestrator` | Coordina la ejecución de un bloque de API: confirma dependencias satisfechas, verifica el gate cross-project si aplica, y delega la implementación a `api-build`. No escribe código. | Sí — si querés saltear el router para un bloque específico. |
| `web-orchestrator` | Coordina la ejecución de un bloque de Web: confirma con `cross-project` que el contract-lock está vigente, y delega la implementación a `web-build`. No mueve un bloque de Web a `ready` sin lock confirmado. | Sí — si querés saltear el router. |
| `cross-project` | Opera la máquina de estados cross-project: congela contratos en `CONTRACT_LOCKS.md` cuando un bloque de API llega a `done`, verifica locks antes de que Web avance, y registra cierres en `CHANGELOG.md`. | Sí — para consultar el estado de un lock o pedir que te muestre un contrato congelado. |
| `git-admin` | Administra el versionado del monorepo (vault raíz + `code/api` + `code/web`): commits prolijos, resolución de repos anidados (submódulo vs. tracking directo), configuración de submódulos, higiene de `.gitignore`. Nunca hace `push`, `reset`, `clean` ni `rm` — esos comandos quedan fuera de su permission set a propósito. | Sí — para pedirle un diagnóstico de los 3 repos o que resuelva un problema de versionado puntual. |

### 🛠️ Implementación

| Agente | Qué hace | ¿Le hablás vos directamente? |
|---|---|---|
| `api-build` | Implementa un único bloque de API contra su tarjeta. Escribe migraciones, modelos, actions, controladores y tests. Corre `composer ci`, pega evidencia real, y pasa el bloque a `verifying`. | No — lo invoca `api-orchestrator`. |
| `web-build` | Implementa un único bloque de Web contra su tarjeta y el contrato congelado. Escribe componentes, hooks, páginas y tests. Verifica visualmente con Playwright antes de reportar. | No — lo invoca `web-orchestrator`. |

### ✅ Verificación

| Agente | Qué hace | ¿Le hablás vos directamente? |
|---|---|---|
| `verifier` | **Verificador independiente.** Re-ejecuta CI, contrasta cada criterio de aceptación contra evidencia real, y hace spot-check de código. Es el único autorizado a mover una tarjeta a `done`. | Sí — para pedirle que revise un bloque en `verifying`. |
| `verify-council` | Verificación por consenso para bloques con `verificacion_critica: true`. Invoca 3 subagentes en paralelo (seguridad, rendimiento, calidad) y produce un veredicto estructurado. El `verifier` usa ese veredicto como input para su decisión final. | No — lo invoca el `verifier` automáticamente cuando la tarjeta lo requiere. |

### 📐 Diseño y documentación

| Agente | Qué hace | ¿Le hablás vos directamente? |
|---|---|---|
| `doc-agent` | Crea el `PANORAMA.md` para features simples, parte features aprobadas en bloques y tarjetas, divide bloques demasiado grandes, y audita coherencia BOARD vs. tarjetas. No aprueba diseños ni mueve tarjetas a `done`. | No directamente — lo invoca `urbania`. Es `hidden: true`. |
| `design-council` | Diseña features de **alta complejidad** por consenso multi-perspectiva. Invoca 3 subagentes en paralelo — arquitectura, UX, seguridad —, anonimiza sus diseños para peer review, y sintetiza un `PANORAMA.md` unificado con sección "Veredicto del Design Council". | No directamente — lo invoca `urbania` según la complejidad del feature. Es `hidden: true`. |
| `adr-council` | Decide decisiones arquitectónicas que requieren un ADR por consenso. Invoca 3 subagentes — optimista (upside), pesimista (riesgos), pragmático (viabilidad) — y produce un ADR con sección "Veredicto del ADR Council". | No directamente — lo invoca `urbania` cuando se necesita documentar una decisión de arquitectura. |

### 🔍 Auditoría y release

| Agente | Qué hace | ¿Le hablás vos directamente? |
|---|---|---|
| `auditor` | Barre todo el vault con 8 checks: BOARD vs. tarjetas, estados válidos, frontmatter, wikilinks rotos, contract locks, evidencia real, gates cross-project, y correspondencia ADRs. Solo lectura — nunca escribe ni modifica archivos. | Sí — "auditá el vault" o "@auditor check contract-locks". También se dispara automáticamente cada 3 bloques `done` y al completar un feature. |
| `release-council` | Evalúa si un feature completo está listo para producción. Invoca 5 subagentes en paralelo (seguridad, datos, UX, rendimiento, regresión) y emite veredicto: 🟢 GO, 🟡 GO CON CONDICIONES, o 🔴 NO-GO. | No directamente — lo invoca `urbania` automáticamente cuando el último bloque de un feature pasa a `done`. |

### 📦 Utilidades internas

| Agente | Qué hace | ¿Le hablás vos directamente? |
|---|---|---|
| `context-reader` | Lee un conjunto acotado de documentos y devuelve un resumen estructurado (`CONTEXTO_INICIO`/`CONTEXTO_FIN`). No interpreta, no sugiere, no decide — solo extrae datos textuales de las tarjetas. | No — lo usan los orquestadores para leer tarjetas antes de delegar. |
| `shell-executor` | Ejecuta comandos de solo lectura (`git status`, `docker compose ps`, `composer show`, `pnpm list`) y devuelve el output verbatim. Sin capacidad de edición ni decisión. | No — lo usan agentes sin acceso a bash para verificar el estado del entorno. |

> **Tip:** El `research-council` y sus subagentes (`optimist-researcher`, `pessimist-researcher`,
> `pragmatist-researcher`) que aparecían en versiones anteriores de esta guía **no existen** en el
> sistema actual — fueron eliminados. Para investigar tecnologías, usá `urbania` directamente o
> consultá la documentación con el MCP `context7`.

---

## 18. Si esta guía y el sistema (`_system/`) no coinciden

Gana `_system/`. Esta guía es una capa de conveniencia sobre esos documentos, no una fuente
independiente — si encontrás una diferencia, es esta guía la que está desactualizada. Corregila (o
pedile a un agente que la corrija) en el mismo momento en que la notes, no la dejes para después.

---

## 19. Credenciales de demo / prueba (dev y QA)

Placeholder — se completa vos mismo cuando el seeder de demo exista (nace con `PROPIEDADES`/
`DIRECTORIO`, Fase 1 del roadmap de negocio que llevás vos por fuera de este vault; ver
`api/API_TESTING.md` §4). Mientras tanto, esta sección queda vacía a propósito — no se inventa un
seeder ni credenciales antes de que el bloque real las cree.

| Rol | Email | Password | Cómo entrar |
|---|---|---|---|
| _(vacío hasta que exista el seeder de demo)_ | | | |

**Cómo se obtienen los códigos que pide el flujo de auth** (invitación, reset de password — ver
`api/API_ARCHITECTURE.md` §9):

- **Mailpit** — `http://localhost:8025` — todo correo saliente en `local`/`testing` queda ahí, es la
  forma "real" de ver lo que vería un residente.
- **Endpoint dev** — `GET /dev/invitations/last?email=...` (y el equivalente de reset de password
  cuando `AUTH-B09` se detalle) — devuelve el token directo, más rápido para `curl`/Playwright. Solo
  existe en `local`/`testing` (ver `api/API_ARCHITECTURE.md` §9) — en cualquier otro entorno, 404.

Esta sección es tuya para mantenerla al día — no la actualiza un agente automáticamente al correr un
seeder, así que si cambian las credenciales de demo, editala vos.
