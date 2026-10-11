---
tipo: adr
id: ADR-040
estado: pendiente
fecha: 2026-10-18
afecta: ["[[RF35]]", "[[RF15]]", "[[RF16]]", "[[RF23]]", "[[RF34]]", "[[Matriz RBAC]]", "[[Modelo entidad-relación]]"]
fuentes: ["Decisión del equipo (17/10/2026)"]
---

# ADR-040 · Solicitud de revisión de calificaciones

## Contexto
El estudiante no tenía un canal en el sistema para pedir que el docente revisara una nota. Los reclamos se resolvían por fuera y sin rastro.

## Decisión
*Definida por el equipo el 17/10/2026; pendiente de aceptación.*

1. **Qué se impugna:**
   - en un cuestionario, preguntas del intento que cuenta (el de mejor nota);
   - en una tarea con rúbrica, criterios;
   - en cualquier actividad, la nota general.

   Cada ítem lleva la explicación del estudiante.
2. **Plazo:** `grade_review_days` (5 días calendario por defecto) desde la publicación de la nota, y antes de que se publique el acta. Con el período cerrado, no.
   - **Cuestionario:** la corrección (respuesta correcta, puntos y justificación) se muestra **sola 10 minutos después del fin efectivo** del cuestionario, que es el cierre o el último intento con tiempo extra ([[ADR-042 Flujo del examen sin depender de Reverb]]; `quiz_review_delay_minutes`), y desde ese momento se puede pedir revisión. No hay botón «Publicar revisión». Un job programado cada minuto (`AvisarRevisionesDisponibles`) avisa una sola vez a los estudiantes.
   - **Prórroga del plazo:** el docente puede ampliar el plazo para pedir revisión a un estudiante; vale la fecha mayor entre «publicación + días» y la prórroga.
3. **Una sola solicitud por actividad, sin retiro**: una vez enviada, el estudiante no la puede retirar (UNIQUE `enrollment_id, assessment_id`).
4. **Quién la resuelve:**
   - **Docente de la sección:** la resuelve.
   - **Coordinador de la carrera:** solo consulta.
   - **Segunda instancia:** no hay por ahora.
5. **Resolución ítem por ítem,** con una respuesta obligatoria en cada uno. Los ajustes recalculan:
   - el intento y la nota (cuestionario);
   - la suma de la rúbrica con la penalización por tardía (tarea);
   - o fijan la nota general: es la **nota final** de la actividad, sin descuentos. En un cuestionario, un recálculo posterior del intento (p. ej. al calificar un ensayo) la reemplaza.
   - La solicitud queda «modificada» solo si la nota cambia; marcar «Ajustar» con el mismo valor la deja «sin cambios».
   - Se resuelve con la solicitud bloqueada en la transacción: no se resuelve dos veces.
6. **Avisos solo en la bandeja** (canal `database`, cola `notificaciones`). Calificaciones y Evaluaciones emiten eventos de dominio (`NotasPublicadas`, `RevisionCuestionarioPublicada`, `RevisionSolicitada`, `RevisionResuelta`) y Comunicación los escucha (`EnviarAvisos`). El envío por Reverb y por correo queda para [[RF23]].
7. **Datos:** `grade_review_requests` y `grade_review_items` (ambas con `section_id`, para que el Jefe vea su auditoría por departamento), auditadas por trigger, con un índice único parcial que impide más de una solicitud no retirada por actividad y matrícula.

8. **Prórrogas** (`assessment_extensions`, una por estudiante, actividad y tipo, auditada):
   - **Entrega de la tarea** (`tareas.gestionar` S): el estudiante entrega hasta la fecha **a tiempo y sin penalización**, aunque la tarea haya cerrado. No aplica a cuestionarios.
   - **Plazo para pedir revisión** (`calificaciones.revision.resolver` S).
   - El estudiante recibe el aviso `extension.granted`.

## Permisos
- `calificaciones.revision.solicitar`: Estudiante, alcance M.
- `calificaciones.revision.resolver`: Docente, alcance S.
- `calificaciones.revision.ver`: Docente S, Coordinador C.
