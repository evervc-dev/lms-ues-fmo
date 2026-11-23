---
tipo: adr
id: ADR-091
estado: pendiente
fecha: 2026-11-22
afecta: ["[[RF38]]", "[[RF16]]", "[[RF18]]", "[[ADR-067 Formato de la regla de finalización de un recurso]]", "[[Modelo entidad-relación]]"]
fuentes: ["Decisión del usuario del 22/11/2026"]
---

# ADR-091 · Lección con cuestionario y lectura comprobada

> [!note] Se numeró ADR-089 en la rama de la lección; ese número ya lo usa [[ADR-089 Una asignatura va en un solo plan de cada carrera]], que entró antes a `test`.

> [!info] Pendiente de aceptar por el equipo. **Implementada** el 22/11/2026 por decisión del usuario, en la rama `contenidos-rf38-leccion`. Amplía [[ADR-067 Formato de la regla de finalización de un recurso]].

## Contexto
El docente quiere que el material y su cuestionario estén en un mismo lugar, que el estudiante no pueda intentar el cuestionario sin haber leído el material y que el intento se abra como examen. Con [[ADR-067 Formato de la regla de finalización de un recurso]] un recurso podía completarse por la nota de un cuestionario, pero no había cómo exigir lo contrario (leer antes de intentar) ni cómo comprobar la lectura más allá de «Ya lo leí».

## Opciones (comprobar la lectura)
- **A.** Tiempo mínimo con la pestaña visible y, si se exige, llegar al final del material.
- **B.** Solo tiempo mínimo.
- **C.** Solo «Ya lo leí».

## Decisión
Decidida por el usuario el 22/11/2026: **A**.

1. **Regla nueva `read`** en `resources.completion_rule`: `{"type": "read", "min_seconds": N, "to_end": bool}`, de 1 a 120 minutos. No se completa sola: el estudiante pulsa «Ya lo leí» y el servidor comprueba la suma de `resource_access_logs.duration_seconds` y, si se exige, un registro `event_type = 'end'`.
2. **Llegar al final** se detecta en una página (un punto al final del texto) y en un PDF en el visor, que en la lección se dibuja con **PDF.js** (`pdfjs-dist`, dependencia nueva) porque el visor del navegador no informa la página. En un archivo que solo se descarga (Word, PowerPoint o un PDF en modo descarga) basta el tiempo.
3. **El cuestionario exige la lectura**: columna `quiz_settings.required_resource_id` (FK a `resources`, `ON DELETE SET NULL` para no impedir la purga de la papelera). `CuestionarioService::iniciar()` rechaza el intento sin `resource_progress` del recurso, aunque se llame por el enlace directo.
4. **Un solo asistente**: «Agregar recurso» crea el recurso y el cuestionario en la misma transacción; si el cuestionario se rechaza, no queda nada (ni el archivo en el disco).
5. **Misma página**: la página del recurso muestra el cuestionario; el estudiante que abre el cuestionario desde otro enlace (Actividades, Mis notas, avisos) va a la lección. El docente sigue viendo la página del cuestionario, con el monitor.
6. **El tiempo de una lección se registra sin cola** (`dispatch_sync`), porque decide al momento si «Ya lo leí» procede.
7. **El cuestionario de la lección es una verificación de lectura y no suma a la nota** (pedido del usuario del 22/11/2026). No lleva cómputo ni peso ni ítem de calificación (`assessments.grade_item_id` nulo), y se marca con `quiz_settings.is_reading_check` para no confundirlo con una evaluación copiada sin programar ([[RF14]]), que tampoco tiene ítem. El resultado del intento queda solo en `quiz_attempts`: el estudiante lo ve como retroalimentación, pero no entra al libro de notas, la boleta, las actas ni las revisiones de calificación. No admite preguntas de ensayo, porque el docente tendría que calificar a mano algo que no cuenta.

## Consecuencias
- Migración `add_lesson_to_quiz_settings_and_access_logs`: `quiz_settings.required_resource_id`, `quiz_settings.is_reading_check` y, en PostgreSQL, el CHECK de `resource_access_logs.event_type` admite `'end'`.
- «Llegar al final» no prueba comprensión; la mide el cuestionario.
- Al reutilizar contenidos ([[RF14]]) la lección se copia sin el cuestionario ligado, como las reglas de [[ADR-067 Formato de la regla de finalización de un recurso]].
- Un navegador no puede impedir que el estudiante salga de la pestaña del examen; queda fuera de esta decisión.
