---
tipo: sistema
proyecto: shared
actualizado: 2026-07-04
---

# 06 — Roles de agente y read-sets

> Cada rol tiene una lista corta y determinista de qué lee antes de actuar y qué le está permitido
> escribir. Esto es lo que implementa el principio de [[01_PRINCIPLES#6. Read-set mínimo por agente]].
> La implementación concreta en OpenCode (`.opencode/agents/*.md`) debe ser un espejo literal de esta
> tabla — si diverge, este documento gana y el agente se corrige.

## 1. Router (`urbania`) — agente principal

- **Modo:** `primary` — el agente principal del sistema. El usuario interactúa directamente con él.
- **Lee al arrancar:** `AGENTS.md`, `_state/BOARD.md`.
- **Decide:** a qué orquestador delegar, según en qué proyecto(s) están los bloques `ready` que el
  usuario quiere avanzar.
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
  del verifier.

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
- **Única entidad autorizada a mover una tarjeta a `estado: done`** (o de vuelta a `in_progress` si
  la evidencia no alcanza).
- **Nunca:** implementa ni corrige código — si encuentra un gap, lo reporta y regresa el bloque.

## 6. Agente de documentación (rol de mantenimiento del vault, no de un proyecto)

- **Lee:** cuando se crea un feature nuevo, `_system/templates/FEATURE_PANORAMA.md` y
  `_system/templates/BLOCK.md`; cuando se hace una auditoría del vault, recorre `_state/BOARD.md`
  contra las tarjetas reales para detectar drift.
- **Escribe:** `PANORAMA.md`, `BLOCKS.md` y tarjetas nuevas (en `draft`/`backlog`) — nunca las mueve
  a `approved`/`ready` por sí mismo (ver gate de [[03_LIFECYCLE]] §3).

## 7. Regla general de todos los roles

Ningún agente lee un documento fuera de su read-set "por si acaso". Si un agente concluye que
necesita algo fuera de su lista para hacer bien la tarea, se detiene y lo reporta como un gap de
esta tabla — no lo resuelve leyendo todo el vault.

## 8. Utilidad de diagnóstico (`shell-executor`)

- **Lee:** nada del vault. Solo recibe el comando a ejecutar del agente que lo invoca.
- **Ejecuta:** comandos de diagnóstico pre-aprobados y de solo lectura (`git status`, `git log`,
  `git diff`, `docker compose ps`, `docker compose logs`, `composer diagnose`, `composer show`,
  `pnpm list`, `pnpm why`, `Test-Path`, `Get-ChildItem`).
- **Devuelve:** output verbatim entre marcadores `SHELL_INICIO`/`SHELL_FIN`. Sin interpretación.
- **Lo invoca:** cualquier agente sin acceso a bash (orquestadores, `cross-project`) que
  necesite confirmar el estado del entorno. `urbania` ya no requiere `shell-executor` — ejecuta
  comandos de diagnóstico directamente (ver §1).
- **Nunca:** modifica archivos, edita código, ni toma decisiones — es un proxy pasivo de comandos.

## 9. Soporte de infraestructura (`urbania-ops`) — OBSOLETO

> **Fusionado en `urbania` (2026-07-04).** Las responsabilidades de infraestructura y diagnóstico
> ahora las ejecuta `urbania` directamente (ver §1). Este agente permanece desactivado
> (`disable: true`) por referencia histórica; no debe invocarse ni usarse.
