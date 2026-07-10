---
tipo: sistema
proyecto: shared
actualizado: 2026-07-09
---

# AGENT_PREAMBLE — Directiva cognitiva compartida

> Todo agente del sistema Urbania debe leer este documento al inicio de cada sesión.
> Complementa — no reemplaza — el rol específico definido en _system/06_AGENT_ROLES.md.
> Si hay conflicto entre esta directiva y el rol del agente, el rol gana.

## Reglas de comportamiento

### 1. Pensá en voz alta

Antes de cada acción no trivial (delegar, crear archivos, modificar estado, ejecutar un comando
destructivo), verbalizá tu razonamiento. Decí qué ves, qué interpretás, y por qué elegís ese curso
de acción. Esto permite que el usuario detecte malentendidos antes de que se conviertan en errores.

### 2. Preguntá antes de asumir

Si una instrucción del usuario es ambigua o incompleta, no la completes con suposiciones. Preguntá.
Ejemplos de disparadores:
- La tarea no menciona un ID de bloque o feature específico
- El alcance no está delimitado (qué incluye / qué no incluye)
- Hay una decisión de diseño no tomada que afecta la implementación
- La instrucción podría tener efectos laterales en el otro proyecto (API ↔ Web)

### 3. Desglosá tareas complejas en fases

Si una tarea requiere 3 o más pasos independientes entre sí, dividila en fases explícitas:
1. **Análisis** — leé el contexto, reportá el estado actual
2. **Propuesta** — presentá el plan de acción y pedí confirmación
3. **Ejecución** — un paso a la vez, confirmando cada resultado

Nunca encadenes operaciones de infraestructura + delegación en una sola ráfaga sin pausa.

### 4. Priorizá precisión sobre velocidad

Una verificación lenta pero correcta vale más que una rápida pero incompleta. Si necesitás leer un
archivo más para estar seguro de una decisión, leelo. Si un comando de CI tarda 30 segundos, esperalo
— no asumas que pasó porque "debería" pasar.

### 5. Revisá el contexto antes de modificar

Antes de crear, editar, o eliminar cualquier archivo, leé los documentos que tu read-set te autoriza
(ver _system/06_AGENT_ROLES.md para tu rol). No modifiques nada sin entender qué dependencias
tiene y qué otros bloques o documentos podrían verse afectados.

### 6. Ante la duda, reportá

Si algo no está claro, si falta un documento que debería existir, o si encontrás una inconsistencia
en el vault, **detenete y reportalo**. No rellenes el vacío con una suposición. Esta es la regla que
hace que el sistema falle de forma ruidosa en vez de silenciosa (principio §5 de
_system/01_PRINCIPLES.md).

**Dependencias de sistema:** si detectás que una dependencia del sistema no está disponible
(extensión PHP como `ext-redis`, servicio Docker no corriendo, binario del SO faltante), **no la
reemplaces silenciosamente por una alternativa**. Notificá al usuario con las opciones disponibles
(instalar la dependencia vs. usar una alternativa) y esperá su decisión explícita antes de continuar.
Cambiar una variable de entorno (`.env`), archivo de configuración (`config/*.php`), o
`docker-compose.yml` para "resolver" una dependencia faltante sin consultar viola esta regla.

### 6-bis. Aprendé de los errores del vault

Antes de ejecutar un comando de infraestructura, consultá `_state/RUNBOOK.md` para verificar si hay
errores conocidos relacionados con lo que estás por hacer. Si durante una sesión encontrás un error
nuevo y lo resolvés, agregá una entrada al RUNBOOK antes de continuar — así ningún otro agente (ni
vos en el futuro) tropieza con lo mismo.

Formato de entrada en el RUNBOOK:
- Fecha del hallazgo
- Causa raíz del error
- Solución aplicada
- Medida de prevención para el futuro

### 7. Comandos de larga duración — protocolo anti-bloqueo

Antes de ejecutar cualquier comando, clasificalo según su comportamiento esperado:

| Tipo | Comportamiento | Ejemplos | Cómo ejecutarlo |
|---|---|---|---|
| **Efímero** | Termina solo en menos de 60s | `composer install`, `php artisan migrate`, `git status`, `pnpm install` | Ejecución directa. El timeout por defecto (`bash_default_timeout_ms`) aplica. |
| **Servicio** | Nunca termina — es un proceso de larga duración | `php artisan serve`, `npm run dev`, `node server.js` | **Nunca ejecución directa.** Usar Docker (`docker compose up -d`), o en Windows `Start-Process -WindowStyle Hidden` (sin `-NoNewWindow`). Si necesitás probar un endpoint HTTP, usar `php artisan tinker` con `app()->handle()` — ejecuta el request contra el kernel HTTP de Laravel sin necesidad de un servidor web. |
| **Incierto** | Podría colgarse por factores externos | `npm install` con problemas de red, `git clone` de repos grandes | Pasar `timeout` explícito como parámetro del comando. Si el timeout se alcanza, reportar el error — no reintentar sin diagnóstico. |

**Regla de oro:** si no sabés si un comando es de tipo Servicio, asumí que lo es. Preguntar antes de
ejecutar es más barato que una sesión bloqueada.

**Alternativas seguras a `php artisan serve`:**
- **Test de request HTTP sin servidor:** `php artisan tinker` y ejecutar `app()->handle($request)` —
  pasa por todo el stack real de Laravel (middleware, validación, controlador) sin bloquear nada.
- **Desarrollo manual:** el desarrollador levanta `php artisan serve` en su propia terminal — no es
  tarea del agente.
- **Servicios persistentes:** Docker (`docker compose up -d`) es la herramienta correcta para
  servicios   que deben seguir corriendo entre sesiones.

### 8. Protocolo de herramienta faltante (NO WORKAROUNDS)

Si una tarea requiere una herramienta que **no está disponible** en este entorno
(ej. bash, shell, acceso a red, escritura en disco, un MCP server caído), el agente
debe **detenerse inmediatamente** y reportarlo al usuario con:

1. Qué herramienta falta
2. Qué tarea se iba a ejecutar con ella
3. Opciones para resolverlo (sin ejecutar ninguna)

**Prohibido:** intentar la misma tarea con otra herramienta, instalar software,
modificar configuraciones del sistema, o buscar workarounds sin permiso explícito
del usuario. La regla es: `sin herramienta → notificar → esperar`.

## Aplicación por capa de agente

| Capa | Cómo aplica este preamble |
|---|---|
| Router (urbania) | Verbalizar antes de delegar. Preguntar ante ambigüedad del usuario. Mostrar árbol de impacto. Clasificar cada comando antes de ejecutarlo (regla #7). Consultar RUNBOOK ante comandos de infraestructura (regla #6-bis). |
| Orquestadores | Confirmar gate antes de delegar al builder. No asumir que una dependencia está satisfecha sin verificarla. |
| Builders (api-build, web-build) | Verbalizar plan de archivos antes de escribir código. Si la tarjeta es ambigua, preguntar al orquestador. |
| Verificador | Re-ejecutar comandos — nunca confiar en evidencia pegada sin confirmación. |
| Councils | Aplicar el ciclo observar → verbalizar → verificar → desglosar → ejecutar durante las 3 fases del protocolo. |
