---
tipo: adr
id: ADR-074
estado: pendiente
fecha: 2026-11-18
afecta: ["[[RF08]]", "[[Matriz RBAC]]", "[[Modelo entidad-relación]]", "[[Coordinador]]", "[[Jefe de Departamento]]", "[[ADR-073 El Jefe crea los grupos teóricos de su departamento]]"]
fuentes: ["Decisión del usuario del 18/11/2026"]
---

# ADR-074 · El grupo teórico pertenece a una carrera y solo lo crean el Administrador y el Jefe

> [!info] Pendiente de aceptar por el equipo. Completa [[ADR-073 El Jefe crea los grupos teóricos de su departamento]].

## Contexto
- El Coordinador podía crear grupos teóricos (`secciones.gestionar` C) y el usuario pidió que solo los creen el Administrador y el Jefe.
- El grupo (`sections`) solo guardaba la materia. Como una materia puede estar en planes de varias carreras (ISI e Industrial, o MAT115), el alcance C del Coordinador («grupos de las materias de los planes de mi carrera») le mostraba también los grupos abiertos para otra carrera.

## Opciones
- **A. Guardar la carrera en el grupo** (`sections.career_id`) y un permiso propio para crear.
- **B. No tocar la base:** no se puede distinguir a qué carrera va un grupo de una materia compartida.

## Decisión
**A.** *Decidida por el usuario el 18/11/2026.*
- Nuevo permiso **`secciones.crear`**: Administrador G, Jefe D. `crearSeccion` lo exige; el Coordinador conserva `secciones.gestionar` C (horario y aula) y `secciones.asignar-docente` C, pero ya no crea grupos.
- Nueva columna **`sections.career_id`** (nullable; FK a `careers` en PostgreSQL), que se llena con la carrera elegida al crear el grupo (carrera → materia de su plan).
- Alcance **C** de grupos (`Alcance::seccionesDeCarreras`): los grupos de su carrera y, de los anteriores sin carrera, los de materias de su plan.

## Consecuencias
- Migraciones `2026_11_18_000300_add_career_id_to_sections` y `2026_11_18_000400_grant_sections_create_permission` (esta concede el permiso en bases con datos).
- En SQLite la columna no lleva FK: agregarla reconstruiría `sections`, de la que depende la vista `v_section_capacity` ([[ADR-033 Migraciones adaptativas]]).
- Los grupos creados antes no tienen carrera: si la materia es compartida, los ven los coordinadores de todas sus carreras hasta que se recreen o se les asigne.
- El reporte por carrera filtra con la misma regla.
