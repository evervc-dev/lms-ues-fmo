---
tipo: adr
id: ADR-065
estado: pendiente
fecha: 2026-11-01
afecta: ["[[RF15]]", "[[RF14]]", "[[RF35]]", "[[Docente]]", "[[Matriz RBAC]]"]
fuentes: ["Decisión del usuario del 01/11/2026", "[[ADR-006 Rúbricas y calificación anónima]]"]
---

# ADR-065 · ¿Dónde se arma la rúbrica?

> [!info] Pendiente de aceptar por el equipo. **Implementada** el 01/11/2026 por decisión del usuario, en la rama `evaluaciones-rf15-rubricas`.

> [!warning] Enmendada el 18/11/2026: la rúbrica ya no tiene niveles
> Al calificar, el docente **escribe los puntos de cada criterio**, de 0 a su máximo, en vez de elegir Excelente (100 %), Bueno (70 %) o Regular (40 %). Los tres niveles encajonaban la nota en tres valores por criterio y el docente no podía reconocer un trabajo que queda entre dos. Lo demás de esta ADR no cambia.
>
> **Consecuencias:** se elimina la tabla `rubric_levels` y la columna `submission_rubric_scores.level_id` (migración `2026_11_18_000000_drop_rubric_levels`). Las notas ya puestas se conservan, porque `points` siempre guardó los puntos; lo que se pierde es la etiqueta del nivel en el desglose histórico que ve el estudiante. La revisión de [[RF35]] también pasa a puntos por criterio.

## Contexto
[[ADR-006 Rúbricas y calificación anónima]] metió las rúbricas en el alcance, pero no dijo dónde se arman. La primera versión las hizo **del docente**: una página aparte, `/rubricas`, con el permiso `rubricas.gestionar`, donde se creaba una lista de rúbricas que después se elegía al crear la tarea.

El usuario lo corrigió: la rúbrica es parte de cada actividad que la necesita, como un reporte, una exposición o un proyecto, y no un catálogo global en otra vista. Los exámenes parciales no llevan rúbrica: se califican pregunta por pregunta.

Además, una rúbrica compartida entre tareas tiene un problema: cambiarla para una tarea cambia las demás, aunque ya tengan notas.

## Opciones
- **A.** Mantener la lista del docente y elegirla en la tarea (la versión original).
- **B.** La rúbrica se arma dentro de la tarea, una por tarea, y queda fija desde la primera calificación.
- **C.** Igual que B, pero también en los exámenes.

## Decisión
**B**, decidida por el usuario el 01/11/2026:
1. **La rúbrica se arma en la tarea:** al crearla («Calificar con rúbrica») o después, en su pantalla de entregas. Los máximos de los criterios suman 10.
2. **Solo las tareas la llevan.** Los exámenes no: se califican por pregunta ([[RF16]]).
3. **Queda fija desde la primera calificación.** Mientras ninguna entrega tenga nota, se puede agregar, cambiar o quitar; después no, para que todas las entregas se califiquen con la misma.
4. **Desaparecen la página Rúbricas y el permiso `rubricas.gestionar`.** La rúbrica se gestiona con `tareas.gestionar` (alcance S).

## Consecuencias
- **Sin cambio de esquema.** `rubrics.owner_id` queda como quién la creó y `assessments.rubric_id` apunta a la rúbrica de esa tarea. La regla de una rúbrica por tarea la aplica `RubricaService`, no un índice único, porque los datos de demostración ya sembrados comparten una rúbrica entre tareas con calificaciones puestas.
- **El bloqueo cuenta todas las tareas que comparten la rúbrica** (solo pasa con esos datos viejos): si cualquiera ya tiene puntajes con ella, no se edita.
- La migración `2026_11_01_000000_revoke_rubrics_manage_permission` quita el permiso de las bases ya sembradas. `DemoAcademicSeeder` crea una rúbrica por tarea.
- **[[RF14]]:** al reutilizar una tarea de un período anterior, su rúbrica se **copia** con ella, en lugar de compartirse.
- [[RF35]] no cambia: la revisión de una tarea con rúbrica sigue recalculando por criterio.
