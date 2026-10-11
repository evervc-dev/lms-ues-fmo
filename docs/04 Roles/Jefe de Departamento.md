---
tipo: rol
nombre: Jefe de Departamento
slug: jefe-departamento
estado: borrador
fuente: Roles y permisos.pdf p.5
---

# Rol · Jefe de Departamento

> **Enfoque:** Evaluar el desempeño docente, supervisar el departamento y autorizar los cierres.

**Alcance de datos:** Su departamento (`department_heads` vigente): analítica, reportes, matrícula y **auditoría** ([[ADR-029 Auditoría departamental del Jefe]]).

**Navegación:** [[Layout Jefe de Departamento]] (menú, vistas y flujos).

## Funcionalidades
### Rendimiento docente
- Panel de KPIs: puntualidad al abrir la asistencia QR, frecuencia de subida de recursos y tiempo promedio de revisión ([[RF33]]).
### Supervisión y trazabilidad
- Ver las carreras de su departamento y entrar a cada una: su Coordinador, los docentes, y las materias con sus grupos y horarios ([[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]).
- Consultar la auditoría de su departamento ([[RF05]]).
- Estadísticas de oferta académica para proyectar grupos ([[RF10]]).
- Inscribir estudiantes en secciones de su departamento ([[RF09]]).
- Autorizar el cierre de su departamento cuando todas las actas están validadas ([[RF34]]).

## Permisos
Las celdas con su alcance están en [[Matriz RBAC]].
- `auditoria.ver`
- `carreras.ver`
- `cierre.autorizar`
- `matricula.gestionar`
- `oferta.estadisticas.ver`
- `reportes.ver`
- `reportes.programar`
- `desempeno-docente.ver`

## Notas
- La «autorización de cierres» departamentales está en [[RF34]].

## Requisitos donde participa
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del rol
    order: [file.name, nombre, prioridad, modulo, estado]
```
