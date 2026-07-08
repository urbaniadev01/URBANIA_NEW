---
tipo: ejemplo
proyecto: shared
actualizado: 2026-07-06
---

# EXAMPLE_VERIFICATION — Verificación bien hecha vs. mal hecha

> **Agente objetivo:** `verifier`. Leer antes de cada verificación.
> Muestra el contraste entre una verificación que sigue el proceso y una que toma atajos.

---

## ✅ BIEN HECHA — Verificación rigurosa

### Entrada: tarjeta AUTH-B01 en `verifying`

```
📊 VERIFICACIÓN — AUTH-B01

### 1. CI re-ejecutado (no confío en evidencia pegada)

> composer ci
  phpstan: OK (level 9, 0 errors)     ← re-ejecutado, no solo leído
  pint: OK
  pest: 4 passed, 0 failed            ← mismo output que la evidencia ✅

CI: ✅

### 2. Criterios de aceptación cubiertos

| # | Criterio | ¿Cubierto? | Dónde |
|---|---|---|---|
| 1 | 201 con invitation_token válido | ✅ | Pest: test_register_with_valid_invitation — assertStatus(201) |
| 2 | 422 si falta token | ✅ | Pest: test_register_fails_with_invalid_token — assertStatus(422) |
| 3 | 409 email duplicado | ✅ | Pest: test_register_fails_with_duplicate_email — assertStatus(409) |
| 4 | 422 password débil | ✅ | Pest: test_register_fails_with_weak_password — assertStatus(422) |
| 5 | Token se marca como usado | ✅ | Pest: assertDatabaseHas con used_at not null |
| 6 | Password hasheado | ✅ | Pest: Hash::check verificado |

Criterios: 6/6 ✅

### 3. Contrato

El bloque produce POST /auth/register.
- `api/endpoints/auth/register.md` existe ✅
- `_state/contracts/CONTRACT_LOCKS.md` tiene entrada AUTH-B01 ✅
- Request/Response documentados con ejemplos ✅

Contrato: ✅

### 4. Revisión de código (spot-check)

- Invitation::class usa `final readonly` ✅
- RegisterUser Action no tiene dependencias de HTTP (Request no se pasa al Action) ✅
- UUID v7 en migración ✅
- Rate limiting: ¿el endpoint tiene throttle? ⚠️ No — no es parte del alcance de esta tarjeta,
  pero lo registro como observación para una tarjeta futura.

### Resultado: ✅ DONE

Observaciones: considerar rate-limiting en endpoint de registro (tarjeta futura).
```

---

## ❌ MAL HECHA — Verificación que toma atajos

### Entrada: misma tarjeta AUTH-B01 en `verifying`

```
📊 VERIFICACIÓN — AUTH-B01

CI: ✅ (el builder dice que pasó)

Criterios: 6/6 ✅ (el builder dice que los cubrió todos)

Contrato: N/A (no revisé CONTRACT_LOCKS.md)

Resultado: ✅ DONE
```

### Qué falló

| Error | Consecuencia |
|---|---|
| No re-ejecutó `composer ci` | Confió en evidencia que podría estar desactualizada o fabricada |
| No verificó cada criterio contra el código/tests | Aceptó un resumen sin confirmar que los tests realmente existen |
| No revisó CONTRACT_LOCKS.md | El lock podría no estar creado → el bloque web que dependa de este endpoint nunca pasaría a `ready` |
| No hizo spot-check de código | No detectó posibles violaciones de convenciones (ej. `final readonly`, UUID v7) |
| Output vago y sin detalle | Si el bloque se rompe en producción, no hay trazabilidad de qué se verificó |

---

## Checklist del verifier (imprimible mentalmente)

Antes de marcar `done`, confirmar:

- [ ] CI re-ejecutado personalmente (no solo leído de la evidencia)
- [ ] Cada criterio de aceptación verificado contra código/tests real
- [ ] Casos negativos cubiertos (no solo el camino feliz)
- [ ] CONTRACT_LOCKS.md revisado (si el bloque produce o consume contrato)
- [ ] Spot-check de convenciones (final readonly, UUID v7, sin dependencias HTTP en Actions)
- [ ] Output detallado con tabla de criterios (no un "todo OK" genérico)
