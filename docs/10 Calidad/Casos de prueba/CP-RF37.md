---
tipo: prueba
rf: "[[RF37]]"
nombre: Mi plan de estudios
prioridad: Media
nivel: feature
casos: 5
estado: automatizado
---

# CP-RF37 · Mi plan de estudios

Casos derivados de los criterios de aceptación de [[RF37]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso | Estado |
|---|---|---|
| CP-RF37-01 | **Dado** un estudiante con asignaturas aprobadas, en curso y reprobadas, **cuando** abre Mi plan, **entonces** ve cada asignatura en su ciclo con el color y la nota de su estado. | automatizado |
| CP-RF37-02 | **Dado** que reprobó y luego aprobó una asignatura, **entonces** sale aprobada con la nota con que la aprobó. | automatizado |
| CP-RF37-03 | **Dado** un estudiante, **cuando** elige otro plan de su carrera, **entonces** ve ese plan con lo ya aprobado marcado. | automatizado |
| CP-RF37-04 | **Dado** el resumen, **entonces** cuenta asignaturas y UV aprobadas y calcula el CUM ponderado por UV. | automatizado |
| CP-RF37-05 | **Dado** un rol sin `plan.ver`, **cuando** abre Mi plan, **entonces** recibe 403. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF37-01 …')`.
