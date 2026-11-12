---
tipo: prueba
rf: "[[RF29]]"
nombre: Auto-matriculación con clave
prioridad: Media-Alta
nivel: feature
casos: 20
estado: pasa
---

# CP-RF29 · Auto-matriculación con clave

Casos derivados de los criterios de aceptación de [[RF29]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF29-01 | **Dado** una clave válida de una asignatura de su carrera y ciclo, **cuando** el estudiante la ingresa, **entonces** ve las secciones abiertas con cupo y sin choque de horario, elige una y queda matriculado con notificación. | pasa |
| CP-RF29-02 | **Dado** que ninguna sección tiene cupo o todas chocan con su horario, **entonces** se le informa y no se matricula. | pasa |
| CP-RF29-03 | **Dado** una asignatura de otra carrera o ciclo, **entonces** no se acepta su clave. | pasa |
| CP-RF29-04 | **Dado** una sección llena, **entonces** se rechaza aunque la clave sea correcta ([[RF09]]). | pasa |
| CP-RF29-05 | **Dado** 5 intentos fallidos seguidos, **entonces** se bloquea por 15 minutos. | pasa |
| CP-RF29-06 | **Dado** el Coordinador o el Jefe, **cuando** cambia o desactiva la clave, **entonces** la anterior deja de funcionar. | pasa |
| CP-RF29-07 | **Dado** las claves por asignatura, **cuando** se busca por código o nombre, **entonces** quedan solo las que coinciden, con aviso si no hay ninguna, y «Limpiar filtros» devuelve la lista. | pasa |
| CP-RF29-08 | **Dado** un estudiante, **cuando** abre «Inscripción de materias», **entonces** ve solo las asignaturas de su carrera del ciclo en curso (ciclo I: ciclos impares del plan; ciclo II: pares) ([[ADR-050 Inscripción por catálogo y claves compartidas]]), **agrupadas por el ciclo del plan** y con **búsqueda por código o nombre** sin distinguir tildes. | automatizado |
| CP-RF29-09 | **Dado** una materia de la lista, **cuando** la abre, **entonces** ve su descripción y sus grupos con docente, horario y cupo, y se inscribe con la clave; una materia de otra carrera o ciclo no se abre (404). | pasa |
| CP-RF29-10 | **Dado** el Coordinador o el Jefe, **cuando** escribe la clave a mano en lugar de generarla, **entonces** esa clave funciona y la anterior deja de funcionar. | pasa |
| CP-RF29-11 | **Dado** el Coordinador o el Jefe, **cuando** usa «Aplicar a todas», **entonces** la misma clave queda en todas las asignaturas de su ámbito y en ninguna fuera de él. | pasa |
| CP-RF29-12 | **Dado** el Jefe de departamento, **entonces** gestiona las claves de las asignaturas de su departamento y no las de otro. | pasa |
| CP-RF29-13 | **Dado** un docente con un grupo de la asignatura en el período, **cuando** edita la descripción de la materia, **entonces** se guarda; un docente sin grupo de esa asignatura recibe 403. | pasa |
| CP-RF29-14 | **Dado** un departamento sin fecha límite, **entonces** el plazo es el inicio del período más las semanas del parámetro `enrollment_deadline_weeks_default` ([[ADR-051 Fecha límite de inscripción por departamento]]). | pasa |
| CP-RF29-15 | **Dado** el Jefe, el Coordinador o el Administrador, **cuando** fija la fecha límite de un departamento de su ámbito, **entonces** se guarda; fuera de su ámbito o con otro rol, 403. | pasa |
| CP-RF29-16 | **Dado** el plazo vencido, **cuando** el estudiante intenta inscribirse con clave, **entonces** se rechaza y la página le informa la fecha. | pasa |
| CP-RF29-17 | **Dado** el plazo vencido, **cuando** el docente, el Coordinador, el Jefe, Soporte o el Administrador inscriben manualmente, **entonces** también se rechaza. | pasa |
| CP-RF29-18 | **Dado** el Jefe, **cuando** prorroga el plazo de su departamento con motivo, **entonces** se vuelve a inscribir y la fecha original se conserva; sin motivo, o si lo intenta el Coordinador, se rechaza. | pasa |
| CP-RF29-19 | **Dado** una prórroga para un estudiante (Coordinador o Jefe, con motivo), **entonces** solo ese estudiante puede inscribirse, con clave o manualmente; los demás siguen bloqueados. | pasa |
| CP-RF29-20 | **Dado** una materia de otro departamento, **entonces** rige la fecha del departamento de la carrera del estudiante, no la del que ofrece la materia. | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF29-01 …')`.
