---
tipo: adr
id: ADR-050
estado: pendiente
fecha: 2026-10-26
afecta: ["[[RF29]]", "[[RF07]]", "[[RF09]]", "[[ADR-032 Ajustes por el documento de roles]]", "[[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]", "[[Matriz RBAC]]"]
fuentes: ["Retroalimentación del cliente y decisión del usuario (26/10/2026)"]
---

# ADR-050 · Inscripción por catálogo y claves compartidas

> [!warning] Pendiente de aceptar
> Amplía [[ADR-032 Ajustes por el documento de roles]] §8 (la clave sigue siendo **por asignatura y período**, pero puede escribirse a mano y repetirse en todas) y la [[Matriz RBAC]] (`matricula.claves.gestionar` pasa a tener también al Jefe, D).

## Contexto
Hasta ahora el estudiante se inscribía desde un **modal** en «Mis materias»: escribía la clave y, si era válida, elegía la sección. El cliente pidió otro recorrido:

1. Una entrada de menú **«Inscripción de materias»**.
2. Una página con las materias **solo de su carrera** y **solo del ciclo en que está** (ciclo I o II del año).
3. Al elegir una, su **descripción** (que escriben los docentes) y el botón **«Inscribirme con clave»**.
4. El **Jefe de departamento** y el **Coordinador de carrera** ponen una clave por materia **o una misma para todas**.

El vault no definía nada de esto. [[RF29]] ya exigía que la clave valiera solo para la carga «de su carrera y ciclo», pero el código solo filtraba la carrera, y `academic_periods` no sabe si un período es ciclo I o II.

## Decisión
*Decidido por el usuario el 26/10/2026.*

**1. Ciclo del período.** El ciclo I va de enero a julio y el ciclo II de agosto a noviembre. Las fechas se repiten cada año, pero se pueden ajustar:
- Parámetro `ciclo_ii_mes_inicio` en `system_settings`, que vale 8 (agosto).
- `PeriodoService::ciclo()` toma el **mes central** entre `start_date` y `end_date`. No usa el mes de inicio porque el ciclo II suele empezar a finales de julio: 2026-II empieza el 27/07.
- No se agrega ninguna columna.

**2. Materias que se listan.**
- En el ciclo I, las asignaturas del plan del estudiante con `career_subjects.cycle` **impar** (1, 3, 5…).
- En el ciclo II, las de ciclo **par**.
- Se usa la paridad y no `student_careers.current_cycle` para que quien va atrasado pueda inscribir materias de años anteriores del mismo ciclo.
- La clave de una asignatura fuera de esa lista no se acepta (CP-RF29-03).

**3. Descripción de la materia.**
- Se guarda en `subjects.description`. La columna ya existía en la migración pero faltaba en el ER.
- La edita el **docente** con `contenidos.gestionar` (S), solo si tiene un grupo vigente de esa asignatura en el período activo. También la edita el Administrador desde el catálogo.
- No se crea un permiso nuevo: es contenido académico de la materia que el docente dicta.
- Si varios docentes dictan la misma asignatura, comparten la descripción.

**4. Claves.**
- `matricula.claves.gestionar` pasa a ser del Coordinador (C, sus carreras) **y del Jefe (D, las asignaturas de su departamento)**.
- La clave se puede **escribir a mano** o **generar**.
- **«Aplicar a todas»** pone la misma clave en todas las asignaturas del ámbito. Se calcula un solo hash y se repite por asignatura, así que se mantiene el UNIQUE parcial `(subject_id, period_id) WHERE is_active`.
- Como el estudiante ya eligió la materia, la clave se compara solo con la de esa asignatura. Por eso puede repetirse sin ambigüedad.

**5. Navegación.**
- `/inscripcion` muestra la lista y `/inscripcion/{asignatura}` el detalle con el modal «Inscribirme con clave».
- «Mis materias» queda solo con lo inscrito.

## Consecuencias
- **[[Matriz RBAC]] y `database/seeders/data/rbac.php`:** el Jefe tiene `matricula.claves.gestionar` (D), y en su menú aparece «Claves de matrícula».
- **[[Modelo entidad-relación]]:** se documenta `subjects.description`.
- **[[Mapa de vistas]] y [[Layout Estudiante]]:** se reemplaza el modal por las dos páginas.
- **CP-RF29-08 a 13**, nuevos.
- **Riesgo:** una clave común para todas las materias es más fácil de filtrar que una por materia. Mitigan el riesgo el bloqueo de 5 intentos, el vencimiento opcional y el filtro por carrera y ciclo.
- **Lo que esta ADR no resuelve:** la «fecha límite de inscripción» de [[RF29]] contradice [[ADR-037 Jerarquía de creación y asignación de roles]], que dice que el período no tiene ventana de matrícula. Se sigue usando solo `enrollment_keys.valid_until`.
