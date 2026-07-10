---
description: Paso 4 de _system/00_START_HERE.md — arrancar el diseño de una feature nueva
argument-hint: <nombre-de-la-feature>
---

Arranca el diseño de la feature `$ARGUMENTS` siguiendo `_system/00_START_HERE.md` Paso 4:

1. Confirma que `features/$ARGUMENTS/` no existe todavía. Si ya existe, repórtalo y detente.
2. Evalúa la complejidad con el usuario:
   - **Simple** (1 endpoint, 1 pantalla): completa `PANORAMA.md` §1–§6 de forma interactiva con el
     usuario.
   - **Compleja** (múltiples endpoints/pantallas, reglas intrincadas): sigue el protocolo de 3 fases
     (divergencia → peer review → síntesis) y agrega una sección "Veredicto del Design Council" antes
     de cerrar el documento.
3. Deja el archivo en `estado_diseño: draft` — **no crees bloques todavía** (gate de
   `_system/03_LIFECYCLE.md` §3).
4. Reporta al usuario que el panorama está listo para revisión y detente ahí. Los bloques
   (`BLOCKS.md` + tarjetas) solo se crean una vez que el usuario pase el estado a `approved`.
