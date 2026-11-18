---
tipo: adr
id: ADR-069
estado: pendiente
fecha: 2026-11-11
afecta: ["[[ADR-025 OpenProject como gestor del trabajo]]", "[[00 Plan de desarrollo]]", "[[OpenProject]]", "[[Roadmap]]"]
fuentes: ["EDT «PROYECTO - API - Inicio» del equipo", "Tabla de integrantes del equipo", "Decisión del usuario del 11/11/2026"]
---

# ADR-069 · El proyecto sigue el plan original por fases y cuatro sprints

> [!info] Pendiente de aceptar por el equipo. **Aplicada en OpenProject** el 11/11/2026 por decisión del usuario. Sustituye la parte «sin sprints» de [[ADR-025 OpenProject como gestor del trabajo]].

## Contexto
[[ADR-025 OpenProject como gestor del trabajo]] fijó un flujo continuo **sin sprints**, con hitos H0 a H4. El plan original del equipo (el EDT «PROYECTO - API - Inicio») organiza el trabajo en diez fases y, dentro del desarrollo, en **cuatro sprints**. OpenProject no reflejaba ese plan: las fases 1 a 3 no tenían fechas ni tablero, el único tablero («Sprint 1») filtraba una versión vacía y usaba grupos como columnas, y las tareas de diseño, integración y cierre no existían.

## Decisión
*Decidido por el usuario el 11/11/2026:*
- Diez fases, en secuencia, con las fechas confirmadas por el equipo:

| EDT | Fase | Fechas |
|---|---|---|
| 1.0 | Inicio del proyecto | 10–12/09/2026 |
| 2.0 | Investigación y recolección de datos | 14–26/09/2026 |
| 3.0 | Análisis, marco teórico y requerimientos | 28/09–09/10/2026 |
| 4.0 | Diseño y arquitectura | 10–17/10/2026 |
| 5.0 | Sprint 1 · Fundamentos, usuarios y seguridad | 19–25/10/2026 |
| 6.0 | Sprint 2 · Gestión académica, contenidos y dispositivos | 26–31/10/2026 |
| 7.0 | Sprint 3 · Evaluaciones, asistencia y comunicación | 02–07/11/2026 |
| 8.0 | Sprint 4 · Asistencia QR, virtual y participación | 09–14/11/2026 |
| 9.0 | Integración y pruebas finales | 16–19/11/2026 |
| 10.0 | Documentación, capacitación y cierre | 20–25/11/2026 |

- En OpenProject, las fases son tareas resumen y los sprints son versiones. Cada RF va al sprint cuyo tema lo incluye en el EDT.
- Un tablero por fase o sprint, con columnas por estado (Pendiente, En progreso, En revisión, Completado).
- Cada tarea tiene una sola persona responsable, tomada de la tabla de integrantes; los grupos solo sirven para filtrar. Si el recurso es solo el Product Owner, la tarea queda sin asignar.
- Las ceremonias de cada sprint (planning, pruebas, revisión cruzada, corrección, review y retrospectiva) son tareas del sprint.

## Consecuencias
- Los hitos H0 a H4 del [[Roadmap]] se conservan como versiones de referencia, pero el avance se sigue por sprint.
- El Gantt muestra las fechas del plan; la fecha real de cada commit o PR queda en el comentario de su tarea.
- Las partes de la tesis que el EDT no tenía (casos de uso, diagrama de clases, diccionario de datos, conclusiones, bibliografía y anexos) se agregaron a las fases 4.0 y 10.0.
- El tablero anterior se renombró «borrador-1» y no se usa.
