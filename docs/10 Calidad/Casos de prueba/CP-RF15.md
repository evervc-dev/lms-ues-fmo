---
tipo: prueba
rf: "[[RF15]]"
nombre: Gestión de tareas, entregas y retroalimentación
prioridad: Media-Alta
nivel: browser + feature
casos: 9
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
| CP-RF15-06 | **Dado** una rúbrica, **cuando** el docente escribe los puntos de cada criterio, **entonces** la nota se calcula automáticamente y ningún criterio admite más de su máximo. | pasa |
| CP-RF15-07 | **Dado** una tarea con **calificación anónima** activada, **cuando** el docente califica, **entonces** ve «Estudiante 1, 2…» en vez de los nombres hasta que publica las notas ([[ADR-032 Ajustes por el documento de roles]]). | pasa |
| CP-RF15-08 | **Dado** una tarea cerrada, **cuando** el docente da una prórroga de entrega a un estudiante, **entonces** ese estudiante entrega hasta la fecha a tiempo y sin penalización; solo el docente de la sección la da, y no existe para cuestionarios ([[ADR-040 Solicitud de revisión de calificaciones]]). | pasa |
| CP-RF15-09 | **Dado** una entrega calificada, **cuando** el estudiante la cancela, **entonces** su nota se vacía y la próxima entrega vuelve a la bandeja del docente. | automatizado |
| CP-RF15-10 | **Dado** una entrega ya calificada, **cuando** el docente la vuelve a abrir, **entonces** ve la nota, los puntos y la retroalimentación que tenía; «Guardar y siguiente» abre la próxima sin nota y se puede pasar a la anterior o la siguiente. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF15-01 …')`.
