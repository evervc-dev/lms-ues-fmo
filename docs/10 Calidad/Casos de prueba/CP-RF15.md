---
tipo: prueba
rf: "[[RF15]]"
nombre: Gestión de tareas, entregas y retroalimentación
prioridad: Media-Alta
nivel: browser + feature
casos: 8
estado: automatizado
---

# CP-RF15 · Gestión de tareas, entregas y retroalimentación

Casos derivados de los criterios de aceptación de [[RF15]]. Nivel: **browser + feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF15-01 | **Dado** una tarea con cierre estricto, **cuando** el estudiante envía un minuto después, **entonces** se rechaza. | automatizado |
| CP-RF15-02 | **Dado** una tarea con fecha de gracia, **cuando** envía dentro de la gracia, **entonces** se acepta y se marca «entregada tarde». | pasa |
| CP-RF15-03 | **Dado** una tarea con gracia y penalización de X %, **cuando** se califica una entrega tardía, **entonces** se descuenta X % automáticamente y se muestra al estudiante. | automatizado |
| CP-RF15-04 | **Dado** un archivo con una extensión o un peso no permitidos, **entonces** se rechaza con un mensaje claro. | automatizado |
| CP-RF15-05 | **Dado** una entrega grupal, **cuando** un integrante entrega, **entonces** cuenta para todo el equipo. | automatizado |
| CP-RF15-06 | **Dado** una rúbrica, **cuando** el docente marca los niveles, **entonces** la nota se calcula automáticamente. | pasa |
| CP-RF15-07 | **Dado** una tarea con **calificación anónima** activada, **cuando** el docente califica, **entonces** ve «Estudiante 1, 2…» en vez de los nombres hasta que publica las notas ([[ADR-032 Ajustes por el documento de roles]]). | pasa |
| CP-RF15-08 | **Dado** una tarea cerrada, **cuando** el docente da una prórroga de entrega a un estudiante, **entonces** ese estudiante entrega hasta la fecha a tiempo y sin penalización; solo el docente de la sección la da, y no existe para cuestionarios ([[ADR-040 Solicitud de revisión de calificaciones]]). | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF15-01 …')`.
