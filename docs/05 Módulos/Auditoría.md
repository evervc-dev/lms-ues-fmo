---
tipo: modulo
nombre: Auditoría
estado: borrador
---

# Módulo · Auditoría

> Trazabilidad inmutable de todo cambio crítico.

## Qué hace
- Registro de **quién, cuándo, desde qué IP, sobre qué entidad, valor anterior y valor nuevo**.
- Cobertura mínima: notas, asistencia, matrícula, períodos, asignación docente, usuarios, roles, dispositivos y configuración.
- Registro de **cada inicio de sesión**, exitoso o fallido.
- Consulta con filtros y exportación: el **Administrador** ve todo y el **Jefe de Departamento** solo su departamento.
- **Inmutable**: nadie edita ni borra.

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
[[Administrador]] · [[Jefe de Departamento]]

## Tablas
[[Modelo entidad-relación#Dashboards, reportes y auditoría|Diagrama ER]]: `audit_logs`, `login_history`

## Flujos
—

## Decisiones
[[ADR-004 Acceso a la auditoría]]

## Notas técnicas
- Triggers de PostgreSQL (`fn_audit_row`) con el actor fijado por `DbContext`; particiones mensuales.
- Rol `app_rw` sin UPDATE ni DELETE sobre `audit_logs`; RLS para el Jefe (solo su departamento).
- Plazo de retención por definir con la UES.
