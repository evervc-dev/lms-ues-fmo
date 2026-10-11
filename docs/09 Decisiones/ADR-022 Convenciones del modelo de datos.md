---
tipo: adr
id: ADR-022
estado: reemplazada
fecha: 2026-10-10
afecta: ["[[Modelo entidad-relación]]", "[[RNF08]]", "[[RNF11]]", "[[RF11]]", "[[RF15]]", "[[RF16]]", "[[RF17]]", "[[RF21]]", "[[RF20]]"]
fuentes: []
---

# ADR-022 · Convenciones del modelo de datos

> [!warning] Reemplazada
> Reemplazada el 10/10/2026 por [[ADR-031 Modelo de datos v2 en inglés]]. Se conserva como historial.

*Decidido por el equipo el 10/10/2026:*

| Tema | Decisión |
|---|---|
| Nombres | Español snake_case. Las tablas de Laravel y de paquetes quedan en inglés |
| Identificadores | `bigint` como PK + `ulid` público en las entidades expuestas en URLs |
| Fechas | `timestamptz` en UTC |
| Borrado lógico | **Solo** `unidades` y `recursos` (papelera). El resto se desactiva o se protege con `ON DELETE RESTRICT` |
| Carrera del estudiante | Una activa (carrera, plan y ciclo en `users`), con historial |
| Cómputos | Por sección, copiados de una plantilla del período y ajustables |
| Ubicación GPS | Solo distancia, precisión y si estaba dentro; **sin coordenadas** |
| Equipos | **Por tarea** |
| Bancos de preguntas | **Del docente**, que puede compartirlos explícitamente |
| Calificaciones | **Tabla única de actividades calificables** (tarea, cuestionario o manual) + `calificaciones` |
| Reporte de Meet | Se guardan el archivo, las filas y el resultado |
| Diccionario de datos | **Solo el diagrama ER**; los campos al detalle viven en las migraciones |

## Consecuencias
- Con bancos por docente, un banco se reutiliza entre períodos ([[RF14]]) siempre que el docente sea el dueño o se lo hayan compartido.
- Sin soft delete en las actividades, una tarea o un cuestionario con entregas o notas **no se puede borrar**, solo ocultar.
- Sin coordenadas, una disputa por GPS se resuelve con la distancia y la precisión guardadas.
