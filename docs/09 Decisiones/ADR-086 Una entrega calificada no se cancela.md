---
tipo: adr
id: ADR-086
estado: pendiente
fecha: 2026-11-22
afecta: ["[[RF15]]", "[[CP-RF15]]"]
fuentes: ["Reporte del usuario del 22/11/2026 (staging, rol Estudiante)"]
---

# ADR-086 · Una entrega calificada no se cancela

> [!info] Pendiente de aceptar por el equipo. **Reemplaza la regla de CP-RF15-09**, que vaciaba la nota al cancelar.

## Contexto
- El estudiante podía cancelar una entrega ya calificada mientras la tarea siguiera abierta. Al cancelar se vaciaba la nota (CP-RF15-09) y podía entregar otra vez: perdía su calificación y obligaba al docente a calificar de nuevo.
- En staging, un estudiante con nota 5.0 veía «Cancelar entrega» junto a su nota.

## Opciones
- **A. Con nota, no se cancela.** Sin nota, se cancela mientras la tarea esté abierta.
- **B. Se cancela y se conserva la nota** hasta que el docente califique la nueva entrega.
- **C. Dejarlo como estaba** (cancelar vacía la nota).

## Decisión
**A.** *Decidida por el usuario el 22/11/2026.*
- Cuenta cualquier nota del docente sobre la entrega, publicada o en borrador; en una tarea grupal, la de cualquier integrante del equipo.
- Se aplica en `ActividadService::cancelarEntrega()` (rechaza con «Su entrega ya fue calificada: no se puede cancelar.») y la pantalla de la tarea deja de ofrecer el botón.

## Consecuencias
- Si el estudiante necesita corregir algo después de calificada, lo pide por la revisión de calificaciones (RF35).
