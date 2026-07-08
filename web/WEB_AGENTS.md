---
tipo: referencia
proyecto: web
actualizado: 2026-07-03
---

# WEB_AGENTS — Entrada local del proyecto Web

> Punto de entrada cuando ya se sabe que la tarea es 100% local a Web (ver
> [[../_system/00_START_HERE]] Paso 1).

## 0. Dónde vive el código

El proyecto Vite vive en `code/web/` como parte de este repositorio (monorepo). Todo comando de
esta página se ejecuta desde ahí, no desde la raíz del vault. Si
`code/web/` todavía no existe, el bloque a ejecutar es
`features/WEB_BOOTSTRAP/blocks/WEB_BOOTSTRAP-B01-instalar-shadcn-tailwind.md` — ningún otro bloque
de Web tiene dónde ejecutarse antes de que ese exista.

## 1. Documentos técnicos de este proyecto

| Documento | Cuándo consultarlo |
|---|---|
| [[WEB_ARCHITECTURE]] | Stack, estructura de carpetas, separación de estado — fuente de verdad de la estructura |
| [[WEB_API_CLIENT]] | Convenciones del cliente HTTP hacia la API, manejo de tokens |
| [[WEB_VISUAL_STANDARDS]] | Design tokens, componentes base |
| [[WEB_TESTING]] | Qué se prueba y cómo |
| `features/<feature-slug>/<FEATURE>-<pantalla>.md` | Detalle de una pantalla específica |

## 2. Reglas de oro (específicas de Web — además de las de `_system/`)

1. **Access token solo en memoria** (store de cliente) — nunca en `localStorage`/`sessionStorage`.
2. **Refresh token solo en cookie `httpOnly`** — Web nunca lo lee ni lo escribe desde JavaScript.
3. **Todo fetch autenticado pasa por el cliente HTTP central** (`WEB_API_CLIENT.md`) — nunca un
   `fetch`/`axios` suelto que reimplemente el manejo de tokens.
4. **Server state solo en TanStack Query** — nunca copiado a Zustand ni a `useState`.
5. **Estado global de cliente (token en memoria, tema, sidebar) solo en Zustand.**
6. **TypeScript strict, cero `any`.**
7. **Un bloque de Web nunca implementa contra un endpoint sin lock vigente** en
   `_state/contracts/CONTRACT_LOCKS.md` (ver [[../_system/04_CROSS_PROJECT]] §3) — si el contrato no
   está congelado, el bloque no está `ready` y no se toca.

## 3. Comandos

```bash
pnpm type-check    # tsc --noEmit
pnpm lint           # eslint . --max-warnings 0
pnpm test           # vitest run
pnpm test:e2e       # Playwright
pnpm build          # tsc -b && vite build
pnpm ci             # type-check + lint + test + build — obligatorio antes de marcar un bloque `verifying`
```

Test de un solo archivo: `pnpm vitest run src/features/auth/foo.test.ts`.
