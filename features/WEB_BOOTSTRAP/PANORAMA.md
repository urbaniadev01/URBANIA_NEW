---
tipo: feature
proyecto: shared
feature: WEB_BOOTSTRAP
estado_diseño: approved
actualizado: 2026-07-03
---

# Feature: WEB_BOOTSTRAP

> **No es una feature de negocio** — es la única excepción del vault: setup técnico de Web que
> ningún feature de negocio debería tener que resolver por su cuenta. Se documenta con el mismo
> mecanismo (panorama + bloque + DoD con evidencia) por consistencia con el resto del sistema, no
> porque tenga reglas de negocio o modelo de datos. Aprobación delegada por el usuario al agente
> para esta sesión puntual, tras decidir [[../../web/adr/ADR-WEB-001-libreria-componentes]].

## 1. Resumen y motivación

Antes de que cualquier bloque de Web con UI (`AUTH-B06`, `AUTH-B07`, y todo lo que siga) pueda
componer una pantalla, necesita una base instalada: librería de componentes, motor de estilos, tema
base. Resolver esto una sola vez, en un bloque dedicado, evita que la primera pantalla de negocio
(`AUTH-B06`) cargue con la responsabilidad de configurar infraestructura además de implementar
login.

## 2. Capas afectadas

- [ ] API
- [x] Web
- [ ] App

## 3. Relación con otras features

- No depende de ninguna feature.
- Es consumido por: `AUTH-B06`, `AUTH-B07`, y todo bloque de Web con UI que se cree en el futuro
  (dependencia implícita de infraestructura, no se declara en `depende_de` de cada bloque salvo que
  ese bloque sea, como estos dos, el primer trabajo real de UI).

## 4. Modelo de datos

No aplica — es setup de frontend, no toca ninguna entidad de negocio.

## 5. Reglas de negocio globales

No aplica.

## 6. Alcance técnico (reemplaza el mapeo a endpoints — no aplica aquí)

- Instalar Tailwind CSS y configurarlo en el proyecto Vite.
- Instalar shadcn/ui (CLI) y generar el set mínimo de componentes base: botón, input, card, modal,
  tabla, form (los que `AUTH-B06`/`AUTH-B07` van a necesitar de entrada).
- Definir el tema base (paleta, tipografía, espaciado) en la configuración de Tailwind — documentado
  en `web/WEB_VISUAL_STANDARDS.md`.

## 7. Plan de bloques

Un único bloque: ver [[BLOCKS]].

## 8. Checklist de aprobación

- [x] Alcance técnico acotado y verificable (§6)
- [x] No introduce vocabulario de dominio nuevo (no toca `shared/GLOSSARY.md`)
- [x] No hay una feature existente que ya cubra esto

> Aprobado — delegación del usuario al agente para esta decisión puntual, registrada aquí.
