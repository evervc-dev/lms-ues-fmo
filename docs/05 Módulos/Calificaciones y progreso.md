---
tipo: modulo
nombre: Calificaciones y progreso
estado: borrador
---

# Módulo · Calificaciones y progreso

> Las notas, la nota final y el avance del estudiante.

## Qué hace
- Libro de calificaciones con escala 0.0–10.0 o rúbrica.
- **Nota final automática** por ponderación y cómputo, con el redondeo institucional.
- Estudiantes **en riesgo** por bajo rendimiento.
- **Corrección de notas**: el docente mientras el período está abierto; con el período **cerrado**, solo el Coordinador con motivo.
- **Progreso por unidad** con finalización manual o automática por reglas.
- Exportación a Excel y PDF; boleta o kardex del estudiante **por cómputo y por unidad**.
- **Actas**: el docente publica, el Coordinador valida y el Jefe autoriza el cierre de su departamento antes de cerrar el período ([[RF34]]).

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
[[Docente]] · [[Estudiante]] · [[Coordinador]] · [[Jefe de Departamento]]

## Tablas
[[Modelo entidad-relación#Calificaciones y progreso|Diagrama ER]]: `grading_component_templates`, `grading_components`, `grade_items`, `grades`, `grade_sheets`, `department_closures`, `enrollment_results`, `academic_alerts`, `system_settings`

## Flujos
[[Flujo Cálculo de nota final]] · [[Flujo Validación de actas y cierre]]

## Decisiones
[[ADR-006 Rúbricas y calificación anónima]]

## Notas técnicas
- Falta el reglamento académico: nota mínima, redondeo y número de cómputos.
- Cambios de nota auditados con el valor anterior y el nuevo.
