---
tipo: sistema
proyecto: shared
actualizado: 2026-07-06
---

# Examples — Few-shot references para agentes

> Cada agente DeepSeek del sistema Urbania se beneficia de ver ejemplos concretos de "cómo se ve
> el trabajo bien hecho". Estos documentos existen para eso — no son documentación para humanos
> (aunque también sirven), son patrones de referencia para LLMs.

## Índice

| Ejemplo | Agente que debe leerlo | Cuándo |
|---|---|---|
| [[EXAMPLE_API_BUILD]] | `api-build` | Antes de la primera implementación de una sesión |
| [[EXAMPLE_VERIFICATION]] | `verifier` | Antes de cada verificación |
| [[EXAMPLE_CROSS_PROJECT]] | `cross-project`, `urbania` | Al gestionar contract locks |

## Cómo usar estos ejemplos

1. El agente lee el ejemplo **antes** de ejecutar su tarea real.
2. No copia el código del ejemplo — el ejemplo es un patrón de formato y nivel de detalle, no una
   plantilla de código.
3. Si el ejemplo contradice una regla explícita del sistema (`_system/`), la regla gana.

## Convención

Cada ejemplo muestra:
- **Entrada:** qué recibió el agente (tarjeta, contexto)
- **Proceso:** qué hizo (comandos ejecutados, archivos creados)
- **Salida:** qué entregó (código, evidencia, veredicto)
- **Errores evitados:** qué habría pasado si el agente hubiera asumido o improvisado
