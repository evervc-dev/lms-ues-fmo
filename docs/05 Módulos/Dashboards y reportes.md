---
tipo: modulo
nombre: Dashboards y reportes
estado: borrador
---

# Módulo · Dashboards y reportes

> Lo que cada rol necesita ver al entrar, y los informes para decidir.

## Qué hace
- **Panel del estudiante**: pendientes primero y calendario, notas, asistencia, progreso y videoconferencias.
- **Panel del docente**: pendientes de calificar (tareas y ensayos), asistencia, estudiantes en riesgo, sesiones sin reporte de Meet.
- **Panel del Coordinador y el Administrador**: matrícula, carga docente, bajo rendimiento e inconsistencias.
- **KPIs docentes** para el Jefe.
- **Reportes** de asistencia, notas, progreso, carga, riesgo y participación en Excel, PDF y gráficos; programables.

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
[[Estudiante]] · [[Docente]] · [[Coordinador]] · [[Jefe de Departamento]] · [[Administrador]]

## Tablas
[[Modelo entidad-relación#Dashboards, reportes y auditoría|Diagrama ER]]: `scheduled_reports`, `exports`, `google_sync_refs`, `v_teacher_kpis`, `mv_offer_stats`

## Flujos
—

## Decisiones
—

## Notas técnicas
- Consultas agregadas cacheadas en Redis.
- Reportes pesados generados en cola y enviados por correo.
