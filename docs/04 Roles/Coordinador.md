---
tipo: rol
nombre: Coordinador
slug: coordinador
estado: borrador
fuente: Roles y permisos.pdf p.4–5
---

# Rol · Coordinador

> **Enfoque:** Gestionar la oferta académica de su carrera, distribuir a los docentes y validar las calificaciones finales.

**Alcance de datos:** Su carrera. Las aulas virtuales las ve solo en modo lectura.

**Navegación:** [[Layout Coordinador]] (menú, vistas y flujos).

## Funcionalidades
### Carga académica
- Asignar y reasignar docentes a las secciones de su carrera ([[RF08]]).
- Configurar claves de matrícula **por asignatura** ([[RF29]]), hacer matrículas manuales y aprobar los cambios de grupo ([[RF09]]).
- Corregir notas de períodos cerrados, con motivo obligatorio ([[RF17]]).
- Aprobar cancelaciones de clases y cambios de modalidad ([[RF32]]).
- Verificar la carga horaria del personal docente ([[RF24]]).
### Supervisión
- Ver en solo lectura las aulas, el progreso y la asistencia de su carrera ([[RF03]]).
- Ver su carrera con sus docentes, y las materias con sus grupos y horarios ([[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]).
- Reportes de asistencia y notas de la carrera ([[RF25]]).
- Validar o devolver las actas de notas de las secciones de su carrera ([[RF34]]).

## Permisos
Las celdas con su alcance están en [[Matriz RBAC]].
- `secciones.gestionar`
- `carreras.ver`
- `secciones.asignar-docente`
- `matricula.gestionar`
- `actas.validar`
- `matricula.claves.gestionar`
- `oferta.estadisticas.ver`
- `contenidos.ver`
- `contenidos.analitica.ver`
- `calificaciones.ver`
- `calificaciones.corregir`
- `progreso.ver`
- `asistencia.ver`
- `clases.cancelar`
- `reportes.ver`
- `reportes.programar`

## Notas
- Rol separado del Jefe ([[ADR-005 Coordinador y Jefe como roles separados]], aceptada).
- La jefatura dijo que la coordinación es «solo un apoyo administrativo al jefe».

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
