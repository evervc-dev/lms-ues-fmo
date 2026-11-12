---
tipo: prueba
rf: "[[RF22]]"
nombre: Alertas de límite de inasistencias
prioridad: Media
nivel: feature
casos: 6
estado: automatizado
---

# CP-RF22 · Alertas de límite de inasistencias

Casos derivados de los criterios de aceptación de [[RF22]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF22-01 | **Dado** un estudiante cuya asistencia del cómputo baja de **85 %**, **entonces** recibe un aviso **preventivo** en la plataforma y por correo. | automatizado |
| CP-RF22-02 | **Dado** que baja de **78 %**, **entonces** recibe un aviso **crítico** y el docente ve la alerta. | automatizado |
| CP-RF22-03 | **Dado** que baja de **75 %**, **entonces** queda «en riesgo por inasistencia» en los paneles del docente y del Coordinador. | automatizado |
| CP-RF22-04 | **Dado** el mismo umbral y cómputo, **entonces** el aviso se envía una sola vez ([[RNF12]]). | automatizado |
| CP-RF22-05 | **Dado** una clase cancelada o una inasistencia justificada, **entonces** no cuenta como falta. | automatizado |
| CP-RF22-06 | **Dado** el docente, **entonces** ve en su panel a los estudiantes en alerta. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF22-01 …')`.
