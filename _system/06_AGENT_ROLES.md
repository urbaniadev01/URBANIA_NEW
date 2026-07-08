---
tipo: sistema
proyecto: shared
actualizado: 2026-07-08
---

# 06 — Roles de agente y read-sets

> Cada rol tiene una lista corta y determinista de qué lee antes de actuar y qué le está permitido
> escribir. Esto es lo que implementa el principio de [[01_PRINCIPLES#6. Read-set mínimo por agente]].
> La implementación concreta en OpenCode (`.opencode/agents/*.md`) debe ser un espejo literal de esta
> tabla — si diverge, este documento gana y el agente se corrige.

## 1. Router (`urbania`) — agente principal

- **Modo:** `primary` — el agente principal del sistema. El usuario interactúa directamente con él.
- **Lee al arrancar:** `AGENTS.md`, `_state/BOARD.md`.
- **Herramientas de diagnóstico:** `codebase-memory` (index_status, search_graph, get_architecture,
  detect_changes, trace_path). Antes de delegar un bloque, consulta `codebase-memory` para enriquecer
  el análisis de impacto con datos reales del código. Al inicio de cada sesión, verifica que el
  índice esté actualizado (`index_status`) — si el índice no incluye `code/`, lo reporta como
  anomalía y ofrece regenerarlo.
- **Decide:** a qué orquestador delegar, según en qué proyecto(s) están los bloques `ready` que el
  usuario quiere avanzar. También decide si una tarea de diseño, arquitectura o release requiere un
  council multi-agente.
- **Ejecuta:** infraestructura y diagnóstico directamente — comandos de Docker, Composer, PNPM, Git
  (operaciones seguras), y operaciones de archivos dentro del workspace. No requiere delegar a un
  agente externo para preparar el entorno.
- **Modelo de seguridad (4 capas):**
  1. **Confinamiento al workspace** — toda operación de archivos opera exclusivamente dentro del
     workspace del proyecto. Rutas externas (`C:\Windows`, `/etc`) se rechazan.
  2. **Operaciones destructivas con confirmación explícita** — `docker compose down -v`,
     `php artisan migrate:fresh`, `Remove-Item -Recurse` requieren confirmación del usuario.
  3. **Sin ejecución de código arbitrario** — no ejecuta scripts descargados de internet, no
     evalúa código dinámico, solo opera binarios del stack del proyecto.
  4. **Sin modificación de configuración del sistema** — no altera variables de entorno del SO,
     no edita archivos fuera del workspace, no instala paquetes del sistema operativo.
- **Permisos bash:** Docker (`compose *`, `ps`, `logs`, `inspect`), Composer (`*`), PHP Artisan
  (`*`), PNPM (`*`), Git (operaciones seguras; `push`, `reset`, `clean` bloqueados), PowerShell
  para archivos (`Get-ChildItem`, `Get-Content`, `Test-Path`, `New-Item`, `Copy-Item`,
  `Rename-Item`; `Remove-Item` requiere confirmación).
- **Lo invoca:** el usuario directamente, en cada sesión.
- **Nunca:** implementa features (modelos, controladores, componentes React) — eso lo delega a los
  orquestadores. No mueve tarjetas de `_state/BOARD.md` — eso es rol exclusivo del orquestador y
  del verifier. No ejecuta verificación de bloques — eso lo hace el verifier (o el `verify-council`
  para bloques críticos).

### Delegación

| Agente | Cuándo delegar |
|---|---|
| `@api-orchestrator` | El bloque `ready`/objetivo del usuario tiene `proyectos: [api]` (o incluye api) |
| `@web-orchestrator` | El bloque tiene `proyectos: [web]` (o incluye web) |
| `@cross-project` | El bloque tiene más de un proyecto y hace falta gestionar el contract-lock |
| `@doc-agent` | La tarea es crear una feature nueva simple, escribir/dividir bloques, o auditar coherencia del vault |
| `design-council` | La tarea es crear el `PANORAMA.md` de un feature nuevo de alta complejidad (múltiples endpoints, pantallas, o reglas de negocio intrincadas) |
| `adr-council` | Se necesita decidir una arquitectura que requiere un ADR nuevo en `shared/adr/`, `api/adr/`, o `web/adr/` |
| `release-council` | El último bloque de un feature pasó a `done` y se necesita veredicto de release antes de marcar `SHIPPED` |
| `git-admin` | La tarea de git excede un comando suelto: commitear trabajo pendiente de forma prolija, resolver un repo anidado (submódulo vs. tracking directo), configurar submódulos, o auditar higiene de `.gitignore` en los 3 repos del monorepo |

### Flujo de inicio (sin tarea específica)

1. Leé `_state/BOARD.md`.
2. Tomá el primer bloque en `ready` de arriba hacia abajo.
3. Delegá al orquestador de su proyecto.
4. Si no hay ningún bloque `ready`, reportalo al usuario — no improvises trabajo fuera del tablero.

### Flujo con tarea específica

Si el usuario menciona un ID de bloque o un feature, andá directo a la tarjeta correspondiente y
delegá al orquestador de su(s) proyecto(s). Si el bloque tiene más de un proyecto, delegá primero a
`@cross-project` para confirmar el gate antes que nada.

## 2. Orquestador de proyecto (`api-orchestrator`, `web-orchestrator`)

- **Lee al arrancar:** `_state/BOARD.md` (filtrado a su proyecto), la tarjeta del bloque específico
  que se va a ejecutar, `<proyecto>/*_AGENTS.md`.
- **Antes de mover un bloque a `in_progress`:** confirma que `estado: ready` en la tarjeta, y si el
  bloque es cross-project, confirma el gate de [[04_CROSS_PROJECT]] §3.
- **Delega:** al agente `*-build` correspondiente para ejecutar el bloque.
- **Al recibir el resultado del build:** no marca `done` directamente — invoca verificación
  independiente (rol §5) antes de cerrar.
- **Nunca:** implementa código ni edita archivos de código — solo coordina y actualiza `_state/`.

## 3. Agente de implementación (`api-build`, `web-build`)

- **Lee al arrancar:** la tarjeta del bloque asignado (única fuente de alcance — no lee el panorama
  completo del feature salvo que la tarjeta lo enlace explícitamente para un dato puntual), los
  documentos de referencia técnica de su proyecto (`api/API_ARCHITECTURE.md`, etc.) que la tarjeta
  cite.
- **Escribe:** código, y al terminar, la sección "Evidencia" de la propia tarjeta del bloque, más
  cualquier documento de referencia que el DoD del bloque señale como afectado
  (`API_CONTRACT.md`, `API_DATABASE.md`, `WEB_API_CLIENT.md`).
- **Cambia el estado de la tarjeta a `verifying`** al terminar — nunca a `done` directamente
  (principio de [[05_DEFINITION_OF_DONE]] §1).
- **Si descubre que el bloque es más grande de lo declarado:** aplica [[03_LIFECYCLE]] §2 (partir en
  bloques nuevos), no extiende el alcance original.

## 4. Agente cross-project (`cross-project`)

- **Lee al arrancar:** `_state/contracts/CONTRACT_LOCKS.md`, la(s) tarjeta(s) de bloque involucradas.
- **Responsabilidad exclusiva:** aplicar la máquina de estados de [[04_CROSS_PROJECT]] — crear/
  actualizar locks, verificar el gate antes de que un bloque de cliente pase a `ready`, escribir la
  entrada de cierre en `_state/CHANGELOG.md`.
- **Nunca:** decide contenido de contrato — solo lo registra una vez que el bloque de API que lo
  produjo está `done`.

## 5. Verificador independiente

- **Lee:** la tarjeta en estado `verifying`, su sección "Evidencia", y re-ejecuta o re-confirma
  contra el checklist real de [[05_DEFINITION_OF_DONE]] para ese proyecto — no confía en el resumen
  del agente implementador.
- **Si la tarjeta tiene `verificacion_critica: true`:** invoca al `verify-council` (§13) y basa su
  decisión en el veredicto consolidado del council. El council no reemplaza al verifier — es un
  input calificado para su decisión final.
- **Única entidad autorizada a mover una tarjeta a `estado: done`** (o de vuelta a `in_progress` si
  la evidencia no alcanza).
- **Nunca:** implementa ni corrige código — si encuentra un gap, lo reporta y regresa el bloque.

## 6. Agente de documentación (rol de mantenimiento del vault, no de un proyecto)

- **Lee:** cuando se crea un feature nuevo, `_system/templates/FEATURE_PANORAMA.md` y
  `_system/templates/BLOCK.md`; cuando se hace una auditoría del vault, recorre `_state/BOARD.md`
  contra las tarjetas reales para detectar drift.
- **Escribe:** `PANORAMA.md`, `BLOCKS.md` y tarjetas nuevas (en `draft`/`backlog`) — nunca las mueve
  a `approved`/`ready` por sí mismo (ver gate de [[03_LIFECYCLE]] §3).
- **Nota:** para features de alta complejidad, el diseño del `PANORAMA.md` lo hace el
  `design-council` (§12) — `@doc-agent` sigue siendo responsable de la partición en bloques y
  tarjetas, y del diseño de features simples.

## 7. Agente de auditoría (`auditor`)

- **Modo:** invocación directa del humano (o del router `urbania` cuando el usuario pida auditoría,
  o automáticamente al cumplirse un trigger de periodicidad).
- **Read-set:** todo el vault — específicamente:
  - `_system/` (completo — principios, convenciones, lifecycle, cross-project, DoD, roles)
  - `_state/BOARD.md` — el rollup a verificar
  - `_state/CHANGELOG.md` — historial de cross-project
  - `_state/contracts/CONTRACT_LOCKS.md` — contratos congelados
  - `features/*/PANORAMA.md` — diseño aprobado de cada feature
  - `features/*/BLOCKS.md` — plan de bloques de cada feature
  - `features/*/blocks/*.md` — todas las tarjetas de bloque (fuente de verdad del estado)
  - `shared/adr/*.md` — decisiones arquitectónicas cross-project
  - `api/adr/*.md`, `web/adr/*.md` — ADRs locales
  - `api/API_ARCHITECTURE.md`, `api/API_CONTRACT.md`, `api/API_DATABASE.md`,
    `api/API_TESTING.md`, `api/endpoints/*.md` — documentación técnica API
  - `web/WEB_ARCHITECTURE.md`, `web/WEB_API_CLIENT.md`, `web/WEB_VISUAL_STANDARDS.md`,
    `web/WEB_TESTING.md`, `web/features/*/*.md` — documentación técnica Web
  - `opencode.json` — configuración de MCPs y herramientas declaradas
- **Ejecuta:** solo operaciones de lectura — `glob`, `grep`, `read`. Para el Check 9, también
  consulta `codebase-memory_index_status` y ejecuta queries de prueba (`search_graph` con
  términos conocidos) para verificar que el índice está operativo.
  **Nunca escribe ni edita ningún archivo.**
- **Produce:** un reporte estructurado con 9 checks, cada hallazgo marcado como
  `✅`, `⚠️` o `❌`. El reporte es texto en la conversación — no se persiste en disco.
- **Lo invoca:** el humano directamente ("auditá el vault", "corré una auditoría de integridad"),
  `urbania` automáticamente al alcanzar el umbral de bloques `done` desde la última auditoría,
  o `urbania` al completarse un feature.
- **Triggers de periodicidad (desacoplados del plan de fases externo):**
  - **Umbral de bloques:** cada 3 bloques marcados `done` desde la última auditoría.
  - **Feature completo:** cuando el último bloque de un feature pasa a `done`.
  - **Pre-lanzamiento cross-project:** mini-auditoría de contract locks antes de mover un bloque de
    cliente a `ready`.
  - **Bajo demanda:** siempre disponible.
- **Verifica 9 checks en orden:**
  1. Drift BOARD vs. tarjetas — cada fila de BOARD coincide con el frontmatter de la tarjeta
  2. Vocabulario de estado — ningún estado fuera de los 6 permitidos
  3. Frontmatter y estructura — todo `.md` tiene frontmatter válido; nombres de archivo correctos
  4. Wikilinks — no hay enlaces rotos a documentos ni secciones
  5. Contract locks — cada lock tiene bloque productor `done`; cada consumidor está registrado;
     ningún bloque web con dependencia de contrato está `ready` sin lock vigente
  6. Evidencia en bloques `done` — cada bloque `done` tiene evidencia real pegada (no afirmaciones)
  7. Gate cross-project — las transiciones de la máquina de estados se respetaron
  8. Correspondencia ADR — las decisiones arquitectónicas documentadas están reflejadas en la
      documentación técnica que les corresponde
  9. Integridad de stack de herramientas — las herramientas externas declaradas en el vault están
     operativas y actualizadas:
     a. `codebase-memory`: el índice existe (`index_status` devuelve `ready`), incluye archivos
        bajo `code/` (no solo documentación), y `search_graph` devuelve resultados para queries
        de prueba (ej. "controller", "middleware").
     b. MCPs declarados: los MCPs listados en `opencode.json` están respondiendo. Si un MCP está
        declarado pero no funciona, es un ⚠️ (anomalía). Si `codebase-memory` está inoperativo
        (sin índice o sin código), es ❌ (crítico — los agentes dependen de él para análisis).
     c. Read-sets actualizados: los agentes que el vault declara como consumidores de
        `codebase-memory` lo tienen explícitamente en su read-set en este documento (§1, §12,
        §13, §15). Si no, es ⚠️.
 - **Si encuentra un `❌`:** no detiene el resto de la auditoría (sigue ejecutando todos los checks),
  pero el reporte final indica severidad general. El `❌` más crítico es siempre **drift BOARD vs.
  tarjetas** (regla #1 del sistema). Las correcciones se aplican de inmediato, no se encolan.
- **Nunca:** mueve estados, edita archivos, implementa código, ni decide qué bloque ejecutar
  después. Su reporte se entrega al humano o a `urbania`.

## 8. Regla general de todos los roles

Ningún agente lee un documento fuera de su read-set "por si acaso". Si un agente concluye que
necesita algo fuera de su lista para hacer bien la tarea, se detiene y lo reporta como un gap de
esta tabla — no lo resuelve leyendo todo el vault.

## 9. Utilidad de diagnóstico (`shell-executor`)

- **Lee:** nada del vault. Solo recibe el comando a ejecutar del agente que lo invoca.
- **Ejecuta:** comandos de diagnóstico pre-aprobados y de solo lectura (`git status`, `git log`,
  `git diff`, `docker compose ps`, `docker compose logs`, `composer diagnose`, `composer show`,
  `pnpm list`, `pnpm why`, `Test-Path`, `Get-ChildItem`).
- **Devuelve:** output verbatim entre marcadores `SHELL_INICIO`/`SHELL_FIN`. Sin interpretación.
- **Lo invoca:** cualquier agente sin acceso a bash (orquestadores, `cross-project`) que
  necesite confirmar el estado del entorno. `urbania` ya no requiere `shell-executor` — ejecuta
  comandos de diagnóstico directamente (ver §1).
- **Nunca:** modifica archivos, edita código, ni toma decisiones — es un proxy pasivo de comandos.

## 10. Context Reader (`context-reader`)

- **Modo:** subagente de solo lectura — lee documentos y devuelve resúmenes sin interpretar ni decidir. Usado por orquestadores.
- **Lee:** documentos del vault que el agente invocador le indique.
- **Nunca:** interpreta, decide, escribe archivos, ni modifica el vault.

## 11. Soporte de infraestructura (`urbania-ops`) — OBSOLETO

> **Fusionado en `urbania` (2026-07-04).** Las responsabilidades de infraestructura y diagnóstico
> ahora las ejecuta `urbania` directamente (ver §1). Este agente permanece desactivado
> (`disable: true`) por referencia histórica; no debe invocarse ni usarse.

## 12. Design Council (`design-council`)

- **Modo:** `primary` — agente de diseño de features por consenso multi-perspectiva.
- **Subagentes:** `design-architect` (arquitectura, datos, escalabilidad), `design-ux` (flujos de usuario, pantallas, experiencia), `design-security` (superficie de ataque, permisos, datos sensibles). Los tres corren en paralelo en la fase de divergencia.
- **Lee al arrancar:** `_system/01_PRINCIPLES.md`, `shared/GLOSSARY.md`, `api/API_ARCHITECTURE.md`, `web/WEB_ARCHITECTURE.md`, y el prompt del usuario con la feature request. Los subagentes leen además los documentos de su especialidad (`api/API_CONTRACT.md`, `web/WEB_VISUAL_STANDARDS.md`, ADRs relevantes).
- **Herramientas:** `codebase-memory` (get_architecture, search_graph, query_graph). En la fase de
  divergencia, el subagente `design-architect` consulta el grafo de conocimiento para entender la
  arquitectura real del código (clustering Leiden, dependencias entre módulos) antes de proponer el
  diseño del feature nuevo.
- **Flujo en 3 fases (protocolo LLM Council):**
  1. **Divergencia:** los 3 subagentes generan diseños independientes en paralelo — cada uno desde su lente (arquitectura, UX, seguridad).
  2. **Peer Review anonimizado:** los 3 diseños se anonimizan como "Diseño A/B/C". Cada subagente recibe los 3 diseños y produce: ranking, fortalezas, debilidades, puntos ciegos.
  3. **Síntesis:** el primario recibe la feature request original, los 3 diseños, y los 3 peer reviews. Produce un `PANORAMA.md` unificado con una sección "Veredicto del Design Council" que documenta puntos de acuerdo, divergencia, y recomendación.
- **Escribe:** `features/<FEATURE>/PANORAMA.md` (en `estado_diseño: draft`). El panorama incluye la sección de veredicto del council como parte del documento.
- **Permisos:** `edit: allow` (solo para escribir `PANORAMA.md`), `bash: deny`, `task: allow` (para invocar subagentes).
- **Lo invoca:** `urbania` cuando el usuario pide crear un feature nuevo de alta complejidad, o el usuario directamente.
- **No reemplaza a `@doc-agent`:** para features simples, `@doc-agent` sigue siendo la ruta de diseño. `urbania` decide según la complejidad declarada.
- **Nunca:** crea bloques ni `BLOCKS.md` — solo el `PANORAMA.md`. La partición en bloques la hace `@doc-agent` después de que el panorama esté `approved`.

## 13. Verify Council (`verify-council`)

- **Modo:** `primary` — agente de verificación por consenso para bloques de alta criticidad.
- **Subagentes:** `sec-reviewer` (seguridad: authZ, inyección, secretos, OWASP), `perf-reviewer` (rendimiento: N+1 queries, memory leaks, race conditions, índices), `code-reviewer` (calidad: DRY, convenciones, cobertura de tests, tipos). Los tres corren en paralelo en la fase de divergencia.
- **Lee al arrancar:** la tarjeta del bloque (con evidencia pegada), `_system/05_DEFINITION_OF_DONE.md`, y el diff del código. Cada subagente lee además documentos de su especialidad: `sec-reviewer` → `api/API_CONTRACT.md` y ADRs de seguridad; `code-reviewer` → guías de estilo y arquitectura del proyecto.
- **Herramientas:** `codebase-memory` (query_graph, trace_path). El subagente `perf-reviewer` usa
  `query_graph` con Cypher para detectar funciones con `transitive_loop_depth >= 2` o
  `linear_scan_in_loop >= 1` — candidatos a N+1 queries y hot paths.
- **Flujo en 3 fases (protocolo LLM Council):**
  1. **Divergencia:** los 3 revisores examinan el código y la evidencia de forma independiente y en paralelo, cada uno desde su lente.
  2. **Peer Review anonimizado:** los hallazgos se anonimizan como "Hallazgos A/B/C". Cada revisor recibe los hallazgos de los otros dos y los confirma, disputa, o amplía.
  3. **Síntesis:** el primario consolida todos los hallazgos y produce un veredicto estructurado: ✅ done, ⚠️ done con observaciones, o ❌ regresa a `in_progress`.
- **Escribe:** el veredicto en la sección "Verificación" de la tarjeta del bloque (vía el verifier — el council reporta, el verifier aplica la decisión en la tarjeta).
- **Permisos:** `edit: deny` (solo reporta; el verifier independiente aplica la decisión), `bash: deny`, `task: allow` (para invocar subagentes).
- **Lo invoca:** el verificador independiente (§5) cuando la tarjeta del bloque tiene `verificacion_critica: true` en su frontmatter.
- **Disparador mecánico:** si `verificacion_critica: true`, el verifier no puede decidir `done` sin pasar por el `verify-council`. Si es `false` (o no está el campo), el verifier opera solo (comportamiento actual).
- **Nunca:** mueve estados de tarjeta, modifica código, ni decide por el verifier — entrega un veredicto calificado, no una orden.

## 14. ADR Council (`adr-council`)

- **Modo:** `primary` — agente de decisión arquitectónica por consenso multi-perspectiva.
- **Subagentes:** `adr-optimist` (upside, oportunidades, visión a largo plazo), `adr-pessimist` (riesgos, costos ocultos, deuda técnica), `adr-pragmatist` (viabilidad real, esfuerzo, dependencias existentes). Los tres corren en paralelo.
- **Lee al arrancar:** `_system/01_PRINCIPLES.md`, el contexto del feature/bloque relevante, los ADRs existentes (`shared/adr/`, `api/adr/`, `web/adr/`), y la arquitectura actual (`api/API_ARCHITECTURE.md`, `web/WEB_ARCHITECTURE.md`).
- **Flujo en 3 fases (protocolo LLM Council):**
  1. **Divergencia:** cada subagente analiza la decisión desde su lente y produce: opciones, tradeoffs, recomendación.
  2. **Peer Review anonimizado:** los análisis se anonimizan. Cada subagente rankea las opciones y documenta puntos de acuerdo/desacuerdo con los otros análisis.
  3. **Síntesis:** el primario produce un ADR que incluye el formato estándar más una sección "Veredicto del ADR Council" con: dónde coincide, dónde diverge, puntos ciegos detectados, recomendación, y primera acción concreta.
- **Escribe:** el archivo ADR (`shared/adr/ADR-NNN-slug.md`, `api/adr/ADR-API-NNN-slug.md`, o `web/adr/ADR-WEB-NNN-slug.md`) usando la plantilla `_system/templates/ADR.md` más la sección de veredicto del council.
- **Permisos:** `edit: allow` (solo para escribir el ADR), `bash: deny`, `task: allow` (para invocar subagentes).
- **Lo invoca:** `urbania` cuando un feature o bloque requiere una decisión arquitectónica documentada, o el usuario directamente.
- **Nunca:** decide por el humano — el ADR queda en estado de revisión hasta que el humano lo aprueba.

## 15. Release Council (`release-council`)

- **Modo:** `primary` — agente de gate pre-deploy por consenso multi-perspectiva.
- **Subagentes (5):** `release-security` (superficie de ataque final, secretos expuestos, authZ), `release-data` (integridad de migraciones, consistencia de datos, rollbacks), `release-ux` (flujos completos, edge cases visuales, accesibilidad), `release-perf` (rendimiento end-to-end, carga esperada), `release-regression` (impacto en features existentes, tests rotos). Los cinco corren en paralelo.
- **Lee al arrancar:** todas las tarjetas `done` del feature, `_state/CHANGELOG.md`, `_state/contracts/CONTRACT_LOCKS.md`, y el reporte de auditoría más reciente. Cada subagente lee documentos de su especialidad (`release-data` → `api/API_DATABASE.md` y migraciones, `release-regression` → tests existentes y endpoints afectados).
- **Herramientas:** `codebase-memory` (detect_changes, trace_path). El subagente `release-regression`
  usa `detect_changes` para identificar el impacto real del feature en el código existente, y
  `trace_path` para mapear qué endpoints y funciones quedaron afectados.
- **Flujo en 3 fases (protocolo LLM Council):**
  1. **Divergencia:** los 5 subagentes evalúan el feature completo desde su lente, en paralelo. Cada uno produce hallazgos independientes clasificados por severidad.
  2. **Peer Review anonimizado:** los hallazgos se anonimizan. Cada revisor recibe los hallazgos de los otros cuatro y los confirma, disputa, o amplía.
  3. **Síntesis:** el primario produce un veredicto consolidado: 🟢 GO, 🟡 GO CON CONDICIONES, o 🔴 NO-GO, con condiciones y riesgos aceptados documentados.
- **Escribe:** nada en disco — el veredicto se entrega como texto en la conversación. Si es GO, `urbania` procede a agregar la entrada de cierre en `CHANGELOG.md`.
- **Permisos:** `edit: deny` (solo reporta), `bash: deny`, `task: allow` (para invocar subagentes).
- **Lo invoca:** `urbania` automáticamente cuando el último bloque de un feature pasa a `done`, o el usuario explícitamente ("¿está listo para producción?").
- **Disparador automático:** al marcarse `done` el último bloque de un feature (según `BLOCKS.md`), `urbania` invoca `release-council` antes de considerar el feature `SHIPPED`.
- **Nunca:** hace deploy, modifica código, ni mueve estados de tarjeta — es un gate consultivo. La decisión final de release es del humano.

## 16. Administrador de git (`git-admin`)

- **Modo:** `subagent` — no es punto de entrada del usuario; lo invoca `urbania`.
- **Lee al arrancar:** `_state/RUNBOOK.md` (errores de git ya documentados) y el estado real de los
  tres repos del monorepo (raíz del vault, `code/api`, `code/web`) vía `git status`/`git log`/
  `git remote` en vivo — nunca asume el estado de una sesión anterior.
- **Responsabilidad:** administrar el versionado del proyecto — commits prolijos de trabajo
  pendiente, resolución de repos anidados (decidir entre convertir a submódulo real o eliminar el
  `.git` interno y trackear directo, siempre presentando ambas opciones al usuario antes de
  ejecutar), configuración de submódulos, higiene de `.gitignore`, y diagnóstico consolidado de los
  tres repos.
- **Permisos bash:** operaciones de git reversibles (`status`, `log`, `diff`, `show`, `remote`,
  `ls-files`, `add`, `commit`, `fetch`, `pull`, `checkout`, `switch`, `stash`, `tag`, `submodule
  add/init/update/sync/status`). **Denegado explícitamente:** `push`, `reset`, `clean`, `rm` — no
  reescribe historial ni borra trabajo sin red de vuelta; si hace falta, reporta el comando exacto
  para que el usuario lo ejecute.
- **Escribe:** `.gitignore`, `.gitmodules`, entradas nuevas en `_state/RUNBOOK.md` cuando documenta
  un incidente de git no registrado.
- **Lo invoca:** `urbania`, cuando la tarea de git excede un comando suelto de diagnóstico (ver
  tabla de delegación en §1).
- **Nunca:** decide el modelo de versionado sin presentar opciones al usuario primero, implementa
  código de features, ni mueve tarjetas de `_state/BOARD.md`.
