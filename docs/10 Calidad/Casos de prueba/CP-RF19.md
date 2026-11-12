---
tipo: prueba
rf: "[[RF19]]"
nombre: Asistencia presencial con múltiples métodos
prioridad: Alta
nivel: feature
casos: 4
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

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF19-01 …')`.
