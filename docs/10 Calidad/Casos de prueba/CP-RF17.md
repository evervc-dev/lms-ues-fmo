---
tipo: prueba
rf: "[[RF17]]"
nombre: Gestión de calificaciones y progreso académico
prioridad: Alta
nivel: feature
casos: 15
estado: automatizado
---

# CP-RF17 · Gestión de calificaciones y progreso académico

Casos derivados de los criterios de aceptación de [[RF17]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF17-01 | **Dado** ponderaciones que no suman 100 %, **cuando** se guardan, **entonces** se rechazan. | automatizado |
| CP-RF17-02 | **Dado** todas las notas de un estudiante, **entonces** la nota final se calcula según la ponderación y el redondeo institucional. | automatizado |
| CP-RF17-03 | **Dado** una actividad sin nota, **cuando** se calcula la nota final, **entonces** cuenta como 0. | automatizado |
| CP-RF17-04 | **Dado** el cierre del período, **entonces** se guarda el resultado oficial con la política aplicada (`policy_snapshot`). | automatizado |
| CP-RF17-05 | **Dado** un estudiante por debajo del mínimo, **entonces** aparece como «en riesgo» en el panel del docente. | automatizado |
| CP-RF17-06 | **Dado** un cambio de nota publicada, **entonces** queda en la auditoría con el valor anterior y el nuevo. | automatizado |
| CP-RF17-07 | **Dado** un período **cerrado**, **cuando** el docente intenta cambiar una nota, **entonces** se rechaza; solo el Coordinador de la carrera puede corregirla, con motivo obligatorio y registro en la auditoría. | automatizado |
| CP-RF17-08 | **Dado** el libro de calificaciones, **entonces** se exporta a Excel o PDF. | automatizado |
| CP-RF17-09 | **Dado** un estudiante, **cuando** consulta su boleta, **entonces** la ve por cómputo y también **por unidad** (actividades ligadas a una unidad). | automatizado |
| CP-RF17-10 | **Dado** una evaluación con calificación anónima, **entonces** los nombres se revelan al publicar las notas. | automatizado |
| CP-RF17-11 | **Dado** el cierre del período, **entonces** las notas pasan por el acta de la sección ([[RF34]]). | automatizado |
| CP-RF17-12 | **Dado** el libro de calificaciones, **cuando** se exporta, **entonces** sale en Excel, PDF y CSV, con una columna por actividad y la nota final; el estudiante matriculado recibe 403 ([[ADR-049 Exportación a Excel y PDF]]). | pasa |
| CP-RF17-13 | **Dado** el período abierto y un cómputo sin ninguna nota publicada, **cuando** el estudiante ve su boleta, **entonces** ese cómputo sale «Pendiente» y no entra en la nota acumulada; al cerrar, cuenta como 0. | automatizado |
| CP-RF17-14 | **Dado** notas en borrador, **cuando** se cierra el período, **entonces** solo entran al resultado oficial las notas publicadas. | automatizado |
| CP-RF17-15 | **Dado** un período cerrado y una nota que faltaba, **cuando** el Coordinador la corrige con motivo, **entonces** se crea publicada y se recalcula el resultado congelado. | automatizado |
| CP-RF17-16 | **Dado** `grade_rounding` en `system_settings`, **entonces** la nota final se redondea con esa regla, la misma que guarda `policy_snapshot`. | automatizado |
| CP-RF17-17 | **Dado** un grupo con los cuatro cómputos de la plantilla (25 % cada uno), **cuando** el docente cambia los pesos, **entonces** puede repartirlos (p. ej. 25 · 25 · 20 · 30) siempre que sumen 100 % y ninguno baje de 20 %; con 15 % se rechaza y no cambia nada ([[ADR-085 Cuatro cómputos por período]]). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF17-01 …')`.
