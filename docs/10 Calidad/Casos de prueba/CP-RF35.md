---
tipo: prueba
rf: "[[RF35]]"
nombre: Solicitud de revisión de una calificación
prioridad: Media
nivel: feature
casos: 8
estado: pasa
---

# CP-RF35 · Solicitud de revisión de una calificación

Casos derivados de los criterios de aceptación de [[RF35]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF35-01 | **Dado** un cuestionario con la nota publicada, **cuando** el estudiante marca una pregunta y explica por qué, **entonces** se crea la solicitud pendiente con los puntos actuales y se avisa al docente. | pasa |
| CP-RF35-02 | **Dado** la nota sin publicar, sin explicación, una segunda solicitud, el plazo vencido, el acta publicada, el período cerrado o antes de 10 minutos del cierre del cuestionario, **entonces** se rechaza con el motivo. | pasa |
| CP-RF35-03 | **Dado** una solicitud de cuestionario, **cuando** el docente sube los puntos de una pregunta, **entonces** se recalculan el intento y la nota, la solicitud queda «modificada» y se avisa al estudiante. | pasa |
| CP-RF35-04 | **Dado** una solicitud de tarea con rúbrica, **cuando** el docente cambia el nivel de un criterio, **entonces** la nota se recalcula con la penalización por tardía; la nota general fijada es la final. | pasa |
| CP-RF35-05 | **Dado** una solicitud, **cuando** el docente responde sin cambios, **entonces** la nota se mantiene (también si «ajusta» al mismo valor); sin respuesta por ítem o con un valor fuera de rango, se rechaza; no se resuelve dos veces. | pasa |
| CP-RF35-06 | **Dado** el Coordinador, otro docente, otro estudiante o un rol sin permiso, **entonces** el Coordinador solo consulta y los demás reciben 403. | pasa |
| CP-RF35-07 | **Dado** la base de datos, **entonces** solo hay una solicitud por actividad y matrícula (UNIQUE); el estudiante no la retira. | pasa |
| CP-RF35-08 | **Dado** el plazo vencido, **cuando** el docente da una prórroga del plazo de revisión a ese estudiante, **entonces** puede pedir revisión hasta esa fecha y recibe el aviso. | pasa |

> **18/10/2026:** automatizados en `tests/Feature/Calificaciones/RevisionTest.php`; pasan en SQLite.
