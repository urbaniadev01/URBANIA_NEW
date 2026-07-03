---
tipo: referencia
proyecto: web
actualizado: 2026-07-03
---

# WEB_TESTING — Estrategia de pruebas

## 1. Capas

| Capa | Qué prueba | Herramienta |
|---|---|---|
| Unit/Component | Componentes y hooks aislados | Vitest + React Testing Library |
| Integración de feature | Un flujo completo de un feature contra API mockeada | Vitest + MSW |
| E2E | Recorrido real de usuario contra la app corriendo | Playwright |

## 2. Regla dura sobre verificación visual

`pnpm ci` (type-check + lint + test + build) confirma tipos y que el código compila — **no**
confirma que la UI se comporta como el criterio de aceptación de la tarjeta dice. Todo bloque que
toca UI requiere, además, una verificación funcional real (Playwright MCP o equivalente) recorriendo
el camino feliz **y** los casos límite de su tabla de criterios de aceptación antes de pasar a
`verifying` — ver [[../_system/05_DEFINITION_OF_DONE]] §3.

## 3. Mocking de API

Los tests de integración de feature usan MSW con handlers que respetan exactamente la forma
congelada en `_state/contracts/CONTRACT_LOCKS.md` — un mock que inventa una forma de respuesta
distinta a la del lock real esconde bugs de integración en vez de atraparlos.
