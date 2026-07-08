---
tipo: estado
proyecto: shared
creado: 2026-07-06
actualizado: 2026-07-07
---

# RUNBOOK — Errores conocidos y soluciones

> Registro vivo de errores encontrados durante la operación del sistema Urbania.
> Cuando un agente o el desarrollador encuentra un error y lo resuelve, se agrega una entrada acá.
> Los agentes deben consultar este archivo **antes** de ejecutar comandos de infraestructura
> (ver `_system/AGENT_PREAMBLE.md` §6-bis y §7).

## Cómo usar este archivo

1. **Consultar antes de ejecutar:** si vas a correr un comando de infraestructura, buscá acá si hay
   entradas con tags relacionados.
2. **Registrar después de resolver:** si encontraste un error nuevo y lo solucionaste, agregá una
   entrada con el formato de abajo **en la misma sesión** — no lo dejes para después.
3. **No duplicar:** si un error ya está documentado, seguí la solución existente. Si la solución
   existente no funcionó, agregá una sub-entrada con tu variante y la fecha.

## Formato de entrada

```markdown
### E-NNN: [Título descriptivo]
- **Fecha:** YYYY-MM-DD
- **Agente/Sesión:** [nombre del agente o "manual"]
- **Causa raíz:** [qué pasó y por qué]
- **Síntoma:** [qué se observó — pantalla colgada, timeout, error específico]
- **Solución:** [qué se hizo para resolverlo]
- **Prevención:** [qué cambio de configuración, proceso o documento evita que esto vuelva a pasar]
- **Tags:** #tag1 #tag2
```

---

## Errores activos

### E-001: Agente bloqueado al ejecutar php artisan serve

- **Fecha:** 2026-07-05
- **Agente/Sesión:** urbania (general)
- **Causa raíz:** `Start-Process -NoNewWindow php artisan serve` lanza un proceso servidor HTTP que
  nunca termina (loop infinito esperando requests). La flag `-NoNewWindow` hereda los streams
  stdout/stderr del agente, haciendo que OpenCode espere indefinidamente la salida del proceso hijo.
  Como el servidor nunca emite una señal de "terminé", la sesión queda bloqueada.
- **Síntoma:** Agente no responde, sesión colgada, sin output de error. Es necesario matar el
  proceso manualmente o reiniciar la sesión.
- **Solución:**
  1. **No usar `php artisan serve` desde el agente.** Es un proceso de tipo "Servicio" — ver
     clasificación en `_system/AGENT_PREAMBLE.md` §7.
  2. Para probar requests HTTP sin servidor: usar `php artisan tinker` y ejecutar
     `app()->handle($request)` — esto pasa por todo el stack real de Laravel (middleware, validación,
     controlador) sin necesidad de un servidor web.
  3. Si es necesario un servidor persistente: usar `docker compose up -d`.
  4. En Windows, si no hay alternativa: `Start-Process -WindowStyle Hidden php artisan serve`
     (sin `-NoNewWindow`) — abre una ventana separada y el agente no espera su salida.
- **Prevención:**
  1. Configurado `bash_default_timeout_ms: 60000` en `opencode.json` — cualquier comando que exceda
     60 segundos será matado automáticamente.
  2. Agregado protocolo de clasificación de comandos en `_system/AGENT_PREAMBLE.md` §7.
  3. Los agentes deben consultar este RUNBOOK antes de ejecutar comandos de infraestructura
     (`_system/AGENT_PREAMBLE.md` §6-bis).
- **Tags:** #bloqueo #php-artisan #infraestructura #windows #start-process #timeout

### E-002: Índice de codebase-memory desactualizado o sin código fuente

- **Fecha:** 2026-07-07
- **Agente/Sesión:** urbania (diagnóstico de infraestructura)
- **Causa raíz:** El índice de `codebase-memory` para `URBANIA_NEW` se generó cuando el vault solo contenía documentación (`.md`), antes de que el código fuente existiera en `code/`. El índice solo tiene 534 nodos estructurales (Section, File, Folder) sin edges de CALLS/DATA_FLOWS. No incluye ningún archivo de `code/api/` ni `code/web/`. Adicionalmente, `search_graph` con BM25 no devuelve resultados — el índice de texto completo no se construyó (posiblemente modo `fast`).
- **Síntoma:** `search_graph` devuelve 0 resultados para cualquier query. `trace_path` no encuentra call chains. `get_architecture` solo muestra estructura de carpetas del vault, no del código. Los agentes que intentan usar `codebase-memory` para análisis de código no obtienen datos útiles.
- **Solución:**
  1. Reindexar el proyecto en modo `full`: `codebase-memory index_repository --repo_path "D:\Programacion\URBANIA_NEW" --mode full`
  2. Verificar que el índice ahora incluye archivos bajo `code/api/` y `code/web/`
  3. Confirmar que `search_graph` devuelve resultados para queries como "login controller" o "auth middleware"
- **Prevención:**
  1. El auditor (Check 9) verifica en cada auditoría que `codebase-memory_index_status` muestra nodos de código y que `search_graph` funciona.
  2. `urbania` ejecuta un health check de `codebase-memory` al inicio de cada sesión (ver `06_AGENT_ROLES.md` §1 read-set actualizado).
  3. Después de cada feature completado, se reindexa (agregado al gatillo de release-council).
- **Tags:** #codebase-memory #indice #infraestructura #diagnostico #mcp

### E-003: Agente cambia REDIS_CLIENT o dependencias de sistema sin notificar al usuario

- **Fecha:** 2026-07-07
- **Agente/Sesión:** urbania (diagnóstico de infraestructura)
- **Causa raíz:** El proyecto Urbania configura `REDIS_CLIENT=phpredis` por defecto (`config/database.php` línea 67), que requiere la extensión PHP `ext-redis` (phpredis) compilada en C vía PECL. Cuando esta extensión no está instalada en el entorno, el agente puede detectar la ausencia y — en lugar de notificar al usuario con opciones — cambiar silenciosamente `REDIS_CLIENT=predis` en `.env`. Esto es una decisión de infraestructura que cambia el comportamiento runtime (cliente Redis en C → PHP puro) sin consentimiento del usuario. El patrón se extiende a cualquier dependencia de sistema cuya ausencia el agente "resuelva" por su cuenta sin consultar.
- **Síntoma:** El usuario no sabe que ocurrió el swap. El proyecto funciona pero con un cliente Redis diferente al configurado por defecto (predis ≈30% más lento que phpredis). La decisión queda invisibilizada — el agente no reporta el hallazgo ni pide confirmación.
- **Solución:**
  1. **Nunca cambiar `REDIS_CLIENT` (ni ninguna variable de entorno que afecte el runtime) sin notificar al usuario.** Protocolo ante ausencia de `ext-redis`:
     - Verificar disponibilidad: `php -r "echo extension_loaded('redis') ? 'yes' : 'no';"`
     - Si no está disponible y `REDIS_CLIENT=phpredis`: **notificar al usuario** con las dos opciones:
       - **A)** Instalar `ext-redis` con `pecl install redis` (más rápido, requiere acceso al sistema y que el PHP tenga el tooling de PECL)
       - **B)** Cambiar `REDIS_CLIENT=predis` en `.env` (predis ya está en composer.json, no requiere instalación adicional, pero es más lento)
     - **Esperar la decisión explícita del usuario** antes de continuar con cualquier operación que dependa de Redis.
  2. Si el usuario elige la opción B, después del cambio ejecutar: `php artisan config:clear`
  3. Este protocolo aplica por extensión a cualquier dependencia de sistema ausente: extensión PHP faltante, servicio Docker no corriendo, binario del SO no disponible. La regla es: **detectar → notificar con opciones → esperar decisión → ejecutar.**
- **Prevención:**
  1. Agregado al RUNBOOK como `E-003`. Los agentes consultan el RUNBOOK antes de ejecutar comandos de infraestructura (`AGENT_PREAMBLE.md` §6-bis).
  2. El agente `urbania` debe ejecutar un health check de entorno al inicio de sesión que incluya verificación de extensiones PHP requeridas: `ext-redis`, `ext-pdo_mysql`, `ext-bcmath`, `ext-openssl`.
  3. Principio general reforzado en `AGENT_PREAMBLE.md` §6: cualquier cambio de configuración de runtime (`.env`, `config/*.php`, `docker-compose.yml`) requiere notificación y confirmación explícita del usuario — nunca es silencioso.
- **Tags:** #ext-redis #redis #phpredis #predis #configuracion #infraestructura #env #notificacion #health-check

---

## Template para nuevas entradas (copiar y pegar)

```markdown
### E-NNN: [Título descriptivo]
- **Fecha:** YYYY-MM-DD
- **Agente/Sesión:** [nombre]
- **Causa raíz:** [qué pasó]
- **Síntoma:** [qué se observó]
- **Solución:** [qué se hizo]
- **Prevención:** [cómo evitar que vuelva a pasar]
- **Tags:** #tag1 #tag2
```
