---
tipo: modulo
nombre: Gestión académica
estado: borrador
---

# Módulo · Gestión académica

> La estructura que sostiene a los cursos: períodos, catálogo, secciones y matrícula.

## Qué hace
- **Períodos** con etapas (planificación, horarios, asignación); un solo período activo; cierre y reapertura auditados; histórico exportable.
- **Catálogo de asignaturas** con código único, créditos, departamento, prerrequisitos y plan de estudios.
- **Secciones** con horario, aula, cupos y uno o más docentes, **sin choques** de docente ni de aula.
- **Matrícula** con clave por asignatura (el estudiante elige la sección) o por el docente, el coordinador y el jefe en su ámbito; se valida el choque de horario y **no** los prerrequisitos. Historial inmutable.
- **Estadísticas de oferta** para proyectar el número de grupos.

## Requisitos
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del módulo
    order: [file.name, nombre, prioridad, estado]
    sort:
      - property: file.name
        direction: ASC
```

## Roles
[[Administrador]] · [[Coordinador]] · [[Jefe de Departamento]] · [[Estudiante]]

## Tablas
[[Modelo entidad-relación#Gestión académica|Diagrama ER]]: `departments`, `careers`, `study_plans`, `department_heads`, `career_coordinators`, `student_careers`, `academic_periods`, `period_calendar_events`, `subjects`, `subject_prerequisites`, `career_subjects`, `classrooms`, `sections`, `enrollment_keys`, `section_schedules`, `teaching_assignments`, `enrollments`, `enrollment_changes`, `section_change_requests`, `class_cancellations`

## Flujos
[[Ciclo de vida del período académico]] · [[Flujo Matrícula y cambio de grupo]]

## Decisiones
—

## Notas técnicas
- Restricción de solapamiento de horario validada en una transacción.
- El calendario de evaluaciones se sincroniza con Google Calendar en cola.
