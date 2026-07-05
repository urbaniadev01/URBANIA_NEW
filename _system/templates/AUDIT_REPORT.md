---
tipo: sistema
proyecto: shared
actualizado: 2026-07-04
---

# Plantilla de reporte de auditoría

> El agente `auditor` produce su salida con este formato exacto. No es un archivo que se rellene —
> es la definición del formato. La salida real va en la conversación, no se persiste en disco.

## Encabezado

```
┌─────────────────────────────────────────────────────────────┐
│  AUDIT LOG · YYYY-MM-DD · trigger: <umbral|feature_complete│
│                              |pre_cross_project|on_demand>  │
└─────────────────────────────────────────────────────────────┘
```

## Tabla de hallazgos

Cada hallazgo es una fila. Severidad binaria: ❌ (hay que arreglarlo antes de seguir) o ⚠️ (avisar
pero no bloquea). No hay más gradaciones.

```
┌─────┬────────────────────────┬──────────┬───────────────────┐
│  #  │ Hallazgo               │ Severidad│ Ref               │
├─────┼────────────────────────┼──────────┼───────────────────┤
│  1  │ <descripción corta>    │ ❌       │ <archivo> L:<nn>  │
│  2  │ <descripción corta>    │ ⚠️       │ <archivo> L:<nn>  │
└─────┴────────────────────────┴──────────┴───────────────────┘
```

## Resumen

```
Resumen: N hallazgos (X ❌, Y ⚠️) · severidad: <OK|ANOMALÍAS|CRÍTICO>
```

La severidad es:
- **OK** — 0 ❌, 0 ⚠️
- **ANOMALÍAS** — 0 ❌, ≥1 ⚠️
- **CRÍTICO** — ≥1 ❌

## Regla de acción

- Si severidad es **CRÍTICO**: no se avanza ni un bloque más hasta que se corrija CADA ❌ y una
  re-auditoría confirme que desaparecieron. Las correcciones se hacen de inmediato, no se encolan.
- Si severidad es **ANOMALÍAS**: se puede seguir, pero conviene revisar los ⚠️ pronto.
- Si severidad es **OK**: el vault está íntegro.
