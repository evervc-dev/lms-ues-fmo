---
tipo: adr
id: ADR-077
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF15]]", "[[RF35]]", "[[Docente]]", "[[Estudiante]]"]
fuentes: ["Decisión del usuario del 21/11/2026 (D5 de la revisión de flujos)"]
---

# ADR-077 · La revisión de una tarea grupal vale para todo el equipo

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- En una tarea grupal la entrega y su rúbrica son del equipo, y la nota se copia a cada integrante, con ajuste individual posible ([[RF15]]).
- Al aceptar la solicitud de revisión de un integrante, se cambiaba la rúbrica compartida pero solo se recalculaba la nota del solicitante: los demás veían el desglose nuevo con su nota vieja.

## Opciones
- **A. Recalcular a todo el equipo**, respetando los ajustes individuales.
- **B. Solo al solicitante**, sin tocar la rúbrica compartida.

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- `RevisionService::recalcularNota` aplica la nota nueva a cada integrante que tenía la misma nota que el solicitante.
- A quien el docente le ajustó la nota a mano (`ajustarIntegrante`), y por eso tiene otra nota, no se le cambia.

## Consecuencias
- Un integrante con ajuste individual que quiera revisión la pide por su cuenta.
- La revisión de un cuestionario no cambia: es individual.
