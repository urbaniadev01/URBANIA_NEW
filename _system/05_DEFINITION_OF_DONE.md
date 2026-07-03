---
tipo: sistema
proyecto: shared
actualizado: 2026-07-03
---

# 05 — Definition of Done (con evidencia)

> "Done" solo existe con evidencia pegada. Este documento define exactamente qué evidencia y quién
> la confirma, por proyecto. Cada tarjeta de bloque copia el checklist relevante en su propia
> sección "Definition of Done" al crearse — este documento es la fuente de esos checklists, no algo
> que se lee aparte durante la ejecución.

## 1. Principio de verificación independiente

El agente que implementa un bloque **reporta** que el DoD se cumplió y pega la evidencia — pero no
es quien mueve la tarjeta a `estado: done`. Esa transición la hace un rol distinto (verificador /
orquestador, ver [[06_AGENT_ROLES]]) que re-ejecuta o al menos re-lee la evidencia de forma
independiente antes de confirmar. Un bloque puede volver de `verifying` a `in_progress` si la
verificación no coincide con lo reportado — eso no es un fallo del proceso, es el proceso
funcionando.

## 2. DoD — proyecto API

- [ ] `composer ci` (lint + stan + test) ejecutado — **salida completa pegada** en la tarjeta, no un
      resumen ("todo pasó").
- [ ] Si el bloque agregó/tocó un endpoint: verificación funcional real con petición y respuesta
      reales pegadas (curl/httpie/Postman), cubriendo **todos** los casos de la tabla de criterios
      de aceptación de la tarjeta — incluidos los casos negativos y de seguridad, no solo el camino
      feliz.
- [ ] Si el bloque agregó una migración: confirmación de que `down()` es reversible (se corrió
      `migrate:rollback` y volvió a `migrate` sin error) — salida pegada.
- [ ] Si el bloque cambió el contrato de un endpoint: entrada creada/actualizada en
      `_state/contracts/CONTRACT_LOCKS.md` (ver [[04_CROSS_PROJECT]]).
- [ ] `api/API_CONTRACT.md` y/o `api/API_DATABASE.md` actualizados si el bloque introdujo algo que
      esos documentos indexan (ver esos documentos para qué exactamente indexan).

## 3. DoD — proyecto Web

- [ ] `pnpm ci` (type-check + lint + test + build) ejecutado — salida completa pegada.
- [ ] Si el bloque tocó UI: verificación visual real (Playwright MCP o equivalente) recorriendo el
      flujo afectado — camino feliz y los casos límite de la tabla de criterios de aceptación.
      `pnpm ci` verifica tipos/lint/build, no comportamiento — no sustituye este paso.
- [ ] Si el bloque consumió un endpoint: confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que
      la integración respeta exactamente el contrato congelado (no una suposición de lo que el
      endpoint "debería" devolver).
- [ ] `web/WEB_API_CLIENT.md` actualizado si el bloque agregó un cliente/hook nuevo hacia un
      endpoint que ese documento indexa.

## 4. DoD — cross-project (bloques con `proyectos: [api, web]`)

Además de los checklists anteriores para cada lado:
- [ ] El bloque de API está `done` y verificado **antes** de que el bloque de cliente pase a
      `in_progress` (mecánico, ver [[04_CROSS_PROJECT]] §3).
- [ ] Entrada de cierre agregada en `_state/CHANGELOG.md` cuando ambos lados llegan a `done`.

## 5. Qué NO cuenta como evidencia

- "Los tests pasan" sin la salida real pegada.
- "Lo probé y funciona" sin el request/response o la captura del flujo.
- Cualquier afirmación del propio agente implementador que reemplace la verificación independiente.

Esta lista existe porque el vault anterior aceptó exactamente estas tres formas de "evidencia" en un
feature marcado como completado que después resultó tener huecos de seguridad reales.
