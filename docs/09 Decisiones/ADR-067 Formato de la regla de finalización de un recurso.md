---
tipo: adr
id: ADR-067
estado: pendiente
fecha: 2026-11-01
afecta: ["[[RF18]]", "[[RF11]]", "[[RF14]]", "[[RF15]]", "[[RF16]]", "[[RF30]]", "[[Modelo entidad-relación]]"]
fuentes: ["Decisión del usuario del 01/11/2026", "[[Planes de implementación pendientes]] (Plan 5)"]
---

# ADR-067 · ¿Cómo se define y cuándo se evalúa la regla de finalización de un recurso?

> [!info] Pendiente de aceptar por el equipo. **Implementada** el 01/11/2026 por decisión del usuario, en la rama `calificaciones-rf18-progreso`.

## Contexto
[[RF18]] pide que el docente elija, por recurso, si se completa **a mano** («ya lo leí») o **solo**, al cumplirse una regla: nota mínima en un cuestionario, entrega de una tarea, N mensajes en un hilo del foro ([[RF30]]) o la apertura del recurso. La columna `resources.completion_rule` (`jsonb`) existía desde la migración de contenidos, pero **sin formato definido**: no estaba decidido qué claves lleva, cuántas reglas admite un recurso, cuándo se evalúa ni qué pasa cuando la regla deja de cumplirse o cuando el recurso se copia a otra sección ([[RF14]]).

## Opciones (formato)
- **A.** Una sola regla por recurso, en un objeto JSON con `type` y las claves de ese tipo.
- **B.** Varias reglas por recurso, en un arreglo, con «todas» o «cualquiera».
- **C.** Tabla aparte `resource_completion_rules`, una fila por regla.

## Decisión
Decidida por el usuario el 01/11/2026:

1. **A · Una regla por recurso**, en `resources.completion_rule`, con `type` y las claves de su tipo. B y C se descartan: ningún criterio de [[RF18]] pide combinar reglas y una tabla nueva no aporta nada mientras la regla sea una.

   | `type` | Claves | Se cumple cuando |
   |---|---|---|
   | `opened` | — | el estudiante abre el recurso |
   | `min_score` | `assessment_id`, `min` (0 a 10, un decimal) | la nota **publicada** del cuestionario es ≥ `min` |
   | `submission` | `assessment_id` | hay una entrega de la tarea con estado `submitted` (en una tarea grupal, la del equipo) |
   | `forum_posts` | `thread_id`, `count` (1 a 20) | el estudiante tiene `count` mensajes **visibles** en ese hilo |

2. **La evaluación y el hilo deben ser de la misma sección** que el recurso, y el tipo de evaluación debe coincidir (`quiz` para `min_score`, `assignment` para `submission`). La evaluación debe estar además **programada** (con cómputo): una sin programar no existe para el estudiante ([[ADR-066 Evaluaciones sin programar al reutilizar]]), así que la regla sería imposible de cumplir y su título aparecería en la página del recurso. Lo valida `ProgresoService::validarRegla()`, que normaliza la nota a un decimal; una regla inválida se rechaza con un mensaje en español.

3. **Se evalúa cuando ocurre el evento** (patrón Observer de [[RNF07]]), con listeners del módulo Contenidos en la cola `default`: `NotasPublicadas`, `IntentoCalificado` (nueva: la nota de un intento nace publicada si la actividad ya estaba publicada, y entonces no habrá un «publicar notas» posterior), `EntregaRealizada` (nueva) y `RespuestaPublicada`. La regla `opened` se resuelve en la propia apertura, sin cola.

4. **Al fijar o cambiar la regla se evalúa de inmediato** para las matrículas activas de la sección: quien ya la cumplía queda completado de forma retroactiva, sin tener que repetir lo hecho.

5. **Un recurso completado no se desmarca**, aunque la regla cambie o deje de cumplirse. El avance es un registro de lo logrado, no un estado reversible; desmarcar obligaría a decidir qué hacer con el avance ya mostrado al estudiante y con el que ve el docente.

6. **Al importar de un período anterior** ([[RF14]], [[ADR-066 Evaluaciones sin programar al reutilizar]]), una regla que apunta a una evaluación o a un hilo **de la sección de origen** no existe en la de destino: el recurso se copia **manual y sin regla**, y el docente la vuelve a fijar desde la página del recurso. `opened` sí se copia tal cual.

7. **Una página sigue siendo automática por omisión** (`opened`), como antes de esta decisión; el resto de los tipos nace manual. El docente lo cambia en el paso «Cómo se completa» al publicar el recurso o después, en la página del recurso.

## Consecuencias
- `ProgresoService` (Contenidos): `validarRegla()`, `describir()` (la regla en palabras para el estudiante), `evaluarRecurso()`, `evaluarPorEvaluacion()`, `evaluarPorHilo()` y `avance()` por unidad.
- `ContenidoService::agregarRecurso()` guarda el modo y la regla y evalúa a quienes ya la cumplen; `cambiarRegla()` la edita; `registrarApertura()` solo completa con la regla `opened`.
- Eventos nuevos `EntregaRealizada` e `IntentoCalificado` en Evaluaciones, con cuatro listeners en `Contenidos/Listeners` registrados en `ContenidosServiceProvider`.
- Pestaña **Progreso** de la Vista de sección con `progreso.ver` (alcance C del Coordinador, S del docente, P del estudiante).
- **Sin migración:** `completion_mode` y `completion_rule` ya existían.
- Una regla `min_score` sobre un cuestionario cuya nota nunca se publica no se cumple nunca: es deliberado, el estudiante ve en la página del recurso qué le falta.
- `marcarLeido()` («ya lo leí») solo acepta recursos **manuales**: el id lo manda el navegador, así que sin esa comprobación un recurso con regla se completaría sin cumplirla.
- Hoy ningún servicio borra evaluaciones ni hilos del foro, así que una regla no puede quedar huérfana. Si alguna vez se permite borrarlos, habrá que decidir qué pasa con la regla que los nombra: quedaría imposible de cumplir.
