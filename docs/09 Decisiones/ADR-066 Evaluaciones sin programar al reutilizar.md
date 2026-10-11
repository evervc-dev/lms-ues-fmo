---
tipo: adr
id: ADR-066
estado: pendiente
fecha: 2026-11-01
afecta: ["[[RF14]]", "[[RF15]]", "[[RF16]]", "[[Modelo entidad-relación]]"]
fuentes: ["Decisión del usuario del 01/11/2026", "[[Planes de implementación pendientes]] (Plan 9)"]
---

# ADR-066 · ¿Cómo queda una evaluación copiada de un período anterior?

> [!info] Pendiente de aceptar por el equipo. **Implementada** el 01/11/2026 por decisión del usuario, en la rama `contenidos-rf14-reutilizacion`.

## Contexto
[[RF14]] pide que una evaluación importada quede «con sus fechas vacías para reprogramarlas». Pero `assessments.opens_at` y `closes_at` eran `NOT NULL`, con `CHECK (closes_at > opens_at)`. Tampoco estaba decidido qué pasa con el cómputo y el peso, con el banco de preguntas de otro docente ni con una segunda importación.

## Opciones (fechas)
- **A.** Fechas opcionales: la evaluación queda «Sin programar» y el docente la programa después.
- **B.** Fechas obligatorias, llenadas en el momento de importar; lo que quede sin fecha no se importa.
- **C.** Fechas provisionales (por ejemplo, el final del período), cerradas al estudiante.

## Decisión
Decidida por el usuario el 01/11/2026:
1. **A · Fechas opcionales.** `opens_at` y `closes_at` aceptan NULL, pero las dos o ninguna (`CHECK assessments_schedule` en PostgreSQL). La evaluación copiada queda **sin programar**.
2. **Sin cómputo.** Se copia con `grade_item_id` en NULL; el docente elige cómputo y peso al programarla, con las mismas validaciones que al crearla (el peso no pasa del 100 % del cómputo). Como todas las consultas de tareas y cuestionarios cruzan con `grade_items`, **una evaluación sin programar no existe para el estudiante**: no aparece en Actividades, ni en el panel ni en pendientes, y no se puede abrir, entregar ni iniciar.
3. **El banco se comparte.** Si el cuestionario usa un banco que no es del docente ni está compartido con él, se agrega a `question_bank_shares`. La rúbrica de una tarea, en cambio, **se copia** con ella ([[ADR-065 La rúbrica es parte de la tarea]]).
4. **No se importa dos veces** la misma sección de origen a la misma sección (lo dice `content_copies`).
5. **Solo de un período anterior** al de la sección de destino, como dice [[RF14]]. Antes se podía copiar de cualquier otra sección, incluso del mismo período.

## Consecuencias
- Migración `2026_11_01_000100_make_assessment_dates_optional` (adaptativa: en SQLite reconstruye la tabla; el CHECK nuevo solo existe en PostgreSQL).
- `ReutilizacionService` (Evaluaciones) copia tareas y cuestionarios con sus ajustes; `grace_until` y la revisión publicada vuelven a cero. No copia entregas, intentos, notas, equipos ni prórrogas.
- `ActividadService::programar()` y la tarjeta «Sin programar» en Actividades. Una tarea con gracia pide también la fecha de gracia.
- Cada recurso con archivo recibe su **copia física**: antes la sección nueva apuntaba al mismo `file_path` y, si se purgaba el original, se quedaba sin archivo.
- Si el dueño de un banco compartido lo edita, el cambio afecta los cuestionarios del otro docente. Es el costo de compartir en vez de copiar.
