---
tipo: adr
id: ADR-092
estado: pendiente
fecha: 2026-11-22
afecta: ["[[RF06]]", "[[RF07]]", "[[CP-RF06]]", "[[CP-RF07]]", "[[Modelo entidad-relación]]"]
fuentes: ["Reporte de QA del 22/11/2026 y decisiones del usuario del mismo día"]
---

# ADR-092 · Archivar períodos, departamentos, carreras y asignaturas

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- Los períodos cerrados se acumulaban en la pantalla principal, y los departamentos y las carreras solo se podían eliminar si nada los usaba: una vez con historial, quedaban visibles para siempre en listas y selectores.
- Las asignaturas ya se podían «desactivar» (`subjects.is_active`), pero el resto de la estructura no tenía un estado equivalente.
- [[ADR-031 Modelo de datos v2 en inglés]] limita el borrado lógico a `units` y `resources`. Archivar no es un borrado: el registro y su historial siguen ahí.

## Opciones
- **A. Archivar con una marca de fecha** (`archived_at`) en períodos, departamentos y carreras, y usar `is_active` en asignaturas.
- **B. Borrado lógico** (`deleted_at`) con Eloquent SoftDeletes.
- **C. Dejarlo como está** (solo eliminar lo que nada usa).

## Decisión
**A.** *Decidida por el usuario el 22/11/2026.*
- **Quién:** el Administrador archiva todo. El Jefe archiva las asignaturas de su departamento, como ya podía desactivarlas ([[ADR-072 El Jefe gestiona las asignaturas de su departamento]]).
- **En uso, se rechaza** y se dice por qué:
  - un departamento, si tiene carreras sin archivar, asignaturas activas o una jefatura vigente;
  - una carrera, si tiene estudiantes activos o una coordinación vigente;
  - una asignatura, si tiene grupos en un período que no está cerrado;
  - un período, si no está cerrado (en PostgreSQL además con un CHECK).
- **Lo archivado no se usa:** sale de las listas y de los selectores (crear carreras, asignaturas, planes y grupos; asignar jefatura, coordinación, perfil docente o carrera del estudiante; importar estudiantes; calendario; oferta académica; informes), y los Services rechazan crear o asignar algo en él.
- **Se ve aparte:** Períodos, Departamentos, Carreras y Catálogo tienen la vista «Archivados», con «Desarchivar». Una carrera no se desarchiva mientras su departamento siga archivado.
- **No borra nada:** notas, actas, asistencia, perfiles y auditoría se conservan. Cada archivo y desarchivo queda auditado.
- En pantalla, «Desactivar asignatura» pasa a llamarse «Archivar».

## Consecuencias
- Migración `add_archived_at_to_periods_departments_careers`.
- Casos CP-RF06-14 y CP-RF07-28 a CP-RF07-30; CP-RF07-02 cambia (una asignatura con grupos en el período en curso ya no se archiva).
- Los perfiles y los informes históricos siguen mostrando el nombre del departamento o la carrera archivados.
