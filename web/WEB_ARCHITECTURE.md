---
tipo: referencia
proyecto: web
actualizado: 2026-07-03
---

# WEB_ARCHITECTURE — Stack y estructura (fuente de verdad)

## 1. Stack

- **Vite 7 + React 19 + TypeScript strict.**
- **SPA pura, 100% detrás de login.** Sin SSR, sin SEO, sin patrones de Next.js (Server Components,
  router de `app/`, `'use client'`) — este proyecto nunca fue ni será Next.js.
- **Componentes: shadcn/ui sobre Tailwind CSS** (ver
  [[adr/ADR-WEB-001-libreria-componentes]]) — Urbania Web es un panel administrativo, no un producto
  con identidad visual pública; se prioriza componer sobre una librería ya resuelta antes que
  diseñar componentes desde cero. Instalado en el bloque `WEB_BOOTSTRAP-B01`.
- Code-splitting con `lazy()` de React Router, a nivel de página de feature — no por componente
  suelto.

## 2. Estructura — feature-based

```
src/
├── app/              # bootstrap, providers globales, router
├── components/
│   ├── ui/             # componentes base generados vía CLI de shadcn/ui (ver ADR-WEB-001)
│   └── ...              # composiciones propias sobre esa base
├── hooks/              # hooks compartidos (no de un feature)
├── lib/                 # utilidades puras
├── services/            # cliente HTTP central (ver WEB_API_CLIENT.md)
├── stores/               # Zustand — solo estado global de cliente
├── types/                 # tipos compartidos
└── features/
    └── <nombre>/           # un feature = una carpeta autocontenida
        ├── api/               # llamadas + hooks de TanStack Query de este feature
        ├── components/
        ├── hooks/
        ├── pages/
        └── types/
```

## 3. Separación estricta de estado — nunca mezclada

| Tipo de estado | Dónde vive | Nunca |
|---|---|---|
| Server state (cualquier dato que viene de la API) | TanStack Query | copiado a Zustand o `useState` |
| Estado global de cliente (token en memoria, tema, sidebar) | Zustand | usado para cachear respuestas de la API |
| Estado local de componente | `useState`/`useReducer` | usado para compartir entre features |

## 4. Layouts por superficie (RBAC-aware, decisión desde el bootstrap)

El panel no es una sola shell — el `scope`/rol resuelto por `AUTH-B05` determina qué layout monta
el router: un administrador ve el panel completo (sidebar con todos los módulos autorizados); un
vigilante ve una consola reducida (portería y lo que su rol permite, sin el resto del menú); un
residente ve el portal agregador. Se decide ahora, no se retrofittea después, porque el roadmap de
negocio (gestionado por el desarrollador fuera de este vault) ya necesita al menos dos superficies
desde su primera entrega: panel admin/residente y consola de vigilante. El primer bloque de UI real
que monte más de un layout es el que crea `src/app/layouts/` con esta estructura; `WEB_BOOTSTRAP-B01`
no crea layouts vacíos por adelantado (no hay pantalla real todavía).

## 5. DevTools — mecanismo de desarrollo (no una pantalla de negocio)

En build de desarrollo (`import.meta.env.DEV`), la app monta un indicador fijo mínimo (esquina,
sin interactividad todavía) que confirma visualmente que se está en modo dev — instalado en
`WEB_BOOTSTRAP-B01` como infraestructura, igual que Mailpit del lado API. Cada bloque de Web que
necesite una acción de conveniencia real (ej. traer el último token de invitación desde
`GET /dev/invitations/last`) la agrega a ese mismo punto de montaje cuando ese bloque exista — no
se construye la funcionalidad por adelantado, solo el mecanismo de que existe un lugar donde vivir.
Nunca se compila en un build de producción (`import.meta.env.PROD` lo excluye del bundle).

## 6. Naming

| Elemento | Convención |
|---|---|
| Componentes | PascalCase, un componente por archivo |
| Hooks | `use<Nombre>` |
| Carpeta de feature | kebab-case (`registro-residentes`) |
| Tipos | PascalCase, sufijo según rol (`LoginRequestDto`, `UserResponse`) |

## 7. Seguridad del lado cliente (implementa el requisito de la API)

Ver [[../shared/SYSTEM_CONTRACT]] §1 y `api/API_ARCHITECTURE.md` §6 para el requisito que la API
define. Del lado Web:

- El **access token** vive únicamente en memoria (un store de Zustand no persistido) — se pierde al
  refrescar la página por diseño; el refresh token en cookie `httpOnly` es lo que permite recuperar
  sesión sin pedir login de nuevo.
- El interceptor del cliente HTTP central excluye explícitamente la llamada de refresh de su propio
  mecanismo de reintento (para no crear un loop de 401 infinito) — ver [[WEB_API_CLIENT]].

## 8. Calidad

- TypeScript strict — cero `any` explícito ni implícito.
- Validación de formularios con Zod + React Hook Form.
- `pnpm ci` (type-check + lint + test + build) es el DoD mínimo de todo bloque, ver
  [[../_system/05_DEFINITION_OF_DONE]] §3.
