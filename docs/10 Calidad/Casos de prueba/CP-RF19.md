---
tipo: prueba
rf: "[[RF19]]"
nombre: Asistencia presencial con múltiples métodos
prioridad: Alta
nivel: feature
casos: 9
estado: automatizado
---

# CP-RF19 · Asistencia presencial con múltiples métodos

Casos derivados de los criterios de aceptación de [[RF19]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF19-01 | **Dado** una sesión, **cuando** el docente marca manualmente, **entonces** puede usar presente, ausente o tardanza. | automatizado |
| CP-RF19-02 | **Dado** un archivo Excel/CSV, **cuando** se importa, **entonces** valida que los estudiantes pertenezcan al grupo. | automatizado |
| CP-RF19-03 | **Dado** una inasistencia, **cuando** el estudiante adjunta una justificación y el docente la acepta, **entonces** el estado cambia a justificado. | automatizado |
| CP-RF19-04 | **Dado** una corrección manual, **entonces** queda en la auditoría con su motivo. | automatizado |
| CP-RF19-05 | **Dado** el horario del grupo, **cuando** el docente abre la asistencia entre 15 min antes del inicio y 15 min después del fin, **entonces** se abre sin pedir motivo ([[ADR-075 La asistencia se abre en el horario del grupo o con motivo]]). | automatizado |
| CP-RF19-06 | **Dado** un momento fuera del horario (otra hora, otro día o fin de semana), **cuando** el docente abre la asistencia, **entonces** se le exige un motivo y con él se abre. | automatizado |
| CP-RF19-07 | **Dado** una apertura fuera del horario, **entonces** su motivo queda en la auditoría (solo PostgreSQL). | automatizado |
| CP-RF19-08 | **Dado** un grupo cuyo período ya no está activo, **cuando** el docente abre, corrige o importa asistencia, **entonces** se rechaza con «El período académico de este grupo no está activo», y la sesión que vence se cierra sin escribir ausentes. | automatizado |
| CP-RF19-09 | **Dado** que ya hubo sesión hoy en el grupo, **cuando** el docente intenta abrir otra, **entonces** se rechaza; al día siguiente se abre ([[ADR-076 Una sesión de asistencia por grupo y día]]). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF19-01 …')`.
