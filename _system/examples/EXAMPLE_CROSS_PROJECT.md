---
tipo: ejemplo
proyecto: shared
actualizado: 2026-07-06
---

# EXAMPLE_CROSS_PROJECT — Ciclo completo de contract-lock

> **Agentes objetivo:** `cross-project`, `urbania`. Ilustra el ciclo completo de un contrato
> entre API y Web: producción → lock → consumo → cierre.
> **Feature de referencia:** AUTH (registro + pantalla de registro).

---

## Fase 1 — API produce el contrato

```
AUTH-B01 (api, POST /auth/register) → estado: done
```

`cross-project` detecta que AUTH-B01 tiene `contrato: produce` en su frontmatter.

### Acción de cross-project

1. Lee la tarjeta de AUTH-B01 — confirma `estado: done`.
2. Lee `api/endpoints/auth/register.md` — obtiene el contrato (request/response).
3. Crea entrada en `_state/contracts/CONTRACT_LOCKS.md`:

```markdown
## AUTH-B01 — POST /auth/register

- **Productor:** AUTH-B01 (api) — estado: done
- **Fecha de congelamiento:** 2026-07-05
- **Endpoint:** `POST /api/auth/register`
- **Request:** `{ invitation_token: string, name: string, email: string, password: string }`
- **Response 201:** `{ data: { token: string, user: { id, name, email } } }`
- **Errores:** 422 (validación), 409 (email duplicado), 410 (invitación expirada/usada)
- **Consumidores:** AUTH-B07 (web) — estado: backlog (esperando este lock)
```

---

## Fase 2 — Web intenta consumir (gate check)

```
web-orchestrator: "¿Puedo mover AUTH-B07 a ready?"
```

`cross-project` aplica la máquina de estados de `_system/04_CROSS_PROJECT.md` §3:

| Check | Resultado |
|---|---|
| ¿AUTH-B01 está `done`? | ✅ Sí |
| ¿Existe lock vigente en CONTRACT_LOCKS.md? | ✅ Sí (creado arriba) |
| ¿El lock coincide con lo que AUTH-B07 declara consumir? | ✅ AUTH-B07 declara `contrato: consume (AUTH-B01)` |

### Respuesta de cross-project

```
✅ GATE ABIERTO — AUTH-B07 puede pasar a ready.

Lock verificado: AUTH-B01 → POST /auth/register (CONTRACT_LOCKS.md línea X)
Productor: done ✅
Consumidor: AUTH-B07 — autorizado a consumir este contrato.

⚠️ Recordatorio: si el contrato cambia, se requiere un NUEVO bloque de API
(04_CROSS_PROJECT §5). Este lock no se edita.
```

---

## Fase 3 — Ambos lados done → cierre

```
AUTH-B01 (api) → done ✅
AUTH-B07 (web) → done ✅  (verifier confirma)
```

`cross-project` agrega entrada de cierre en `_state/CHANGELOG.md`:

```markdown
## 2026-07-06 — AUTH: registro por invitación (cross-project cerrado)

- **API:** AUTH-B01 — POST /auth/register → done
- **Web:** AUTH-B07 — pantalla de registro → done
- **Lock:** AUTH-B01 (CONTRACT_LOCKS.md) — cerrado, ambos lados completos
```

---

## Errores comunes que este ejemplo evita

| Error | Cómo se evita |
|---|---|
| Web empieza a implementar antes de que el lock exista | ❌ `web-orchestrator` siempre consulta a `cross-project` antes de mover a `ready`. Sin lock = se queda en `backlog`. |
| API cambia el contrato silenciosamente | ❌ `04_CROSS_PROJECT` §5: si hay locks activos, el cambio requiere un bloque NUEVO de API, nunca editar el lock. |
| No se documenta el cierre en CHANGELOG | ❌ `cross-project` verifica: ¿ambos lados `done`? → entrada en CHANGELOG. |
| Web asume el formato del endpoint sin leer el lock | ❌ `web-build` recibe el contenido del lock en su prompt de delegación — no adivina. |
