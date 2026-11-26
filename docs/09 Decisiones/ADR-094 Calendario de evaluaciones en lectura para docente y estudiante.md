---
tipo: adr
id: ADR-094
estado: pendiente
fecha: 2026-11-24
afecta: ["[[RF06]]", "[[RF24]]", "[[Matriz RBAC]]", "[[ADR-068 Calendario de evaluaciones por ámbito]]"]
fuentes: ["LMS UES - Errores y sugerencias de mejora (QA 24/11/2026)", "Pedido del usuario del 24/11/2026"]
---

# ADR-094 · Calendario de evaluaciones en lectura para docente y estudiante

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- [[ADR-068 Calendario de evaluaciones por ámbito]] da el calendario solo a quien lo gestiona (`periodos.calendario.gestionar`: Administrador G, Jefe D, Coordinador C).
- El Docente y el Estudiante solo se enteraban de las fechas si alguien las sincronizaba con Google Calendar; en la plataforma no podían consultarlas.
- La revisión de QA del 24/11/2026 pidió que ambos vean el calendario.

## Opciones
- **A. Permiso nuevo `periodos.calendario.ver`** (Docente S, Estudiante M), en la misma pantalla y en solo lectura.
- **B. Dar `gestionar` a todos** y limitar en la vista lo que se puede hacer. Se descarta, porque el permiso dejaría de decir lo que la persona puede hacer.
- **C. Dejarlo como está.**

## Decisión
**A.** *Decidida por el usuario el 24/11/2026.*

- La ruta `periodos.calendario` acepta `periodos.calendario.gestionar|periodos.calendario.ver`.
- `CalendarioPeriodoService` muestra a quien solo consulta:
  - las fechas globales del período;
  - las fechas del departamento de las materias de sus grupos teóricos o de sus matrículas activas;
  - las fechas de las carreras que llevan esas materias en su plan ([[ADR-080 El grupo teórico es de la asignatura y lo comparten las carreras]]);
  - las fechas de la carrera en que estudia.
- Sin `gestionar` no aparecen «Agregar fecha», «Eliminar», «Sincronizar» ni la columna de Google Calendar. El Service sigue rechazando esas acciones con 403.
- El menú de Docencia y el de Estudio tienen la opción «Calendario de evaluaciones».
- El Inicio del Docente y del Estudiante muestra «Próximas evaluaciones».

## Consecuencias
- Hay un permiso más en la [[Matriz RBAC]] y en el catálogo de permisos.
- Las pruebas de `CalendarioPeriodoTest` que esperaban 403 para el Docente y el Estudiante ahora comprueban la lectura y que no pueden cambiar nada.
