---
tipo: adr
id: ADR-080
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF08]]", "[[RF09]]", "[[RF32]]", "[[RF34]]", "[[Matriz RBAC]]", "[[Modelo entidad-relación]]", "[[Coordinador]]", "[[ADR-074 El grupo teórico pertenece a una carrera y solo lo crean el Administrador y el Jefe]]"]
fuentes: ["Decisión del usuario del 21/11/2026 (D2 de la revisión de flujos)"]
---

# ADR-080 · El grupo teórico es de la asignatura y lo comparten las carreras

> [!info] Pendiente de aceptar por el equipo. Reemplaza en parte a [[ADR-074 El grupo teórico pertenece a una carrera y solo lo crean el Administrador y el Jefe]].

## Contexto
- En la facultad rige la asignatura: si una materia está en el plan de dos carreras, sus grupos (01, 02…) los cursan estudiantes de ambas. El estudiante ya se inscribe así: con la clave de la asignatura elige cualquier grupo de la materia ([[ADR-078 El Jefe resuelve el cambio de grupo y no se validan prerrequisitos]]).
- ADR-074 guardó una carrera en cada grupo (`sections.career_id`) y con ella limitaba al personal. El Coordinador de la otra carrera no veía el grupo donde estaban sus estudiantes, y una cancelación por carrera no alcanzaba a los grupos marcados con otra.

## Opciones
- **A. El grupo es de la asignatura:** el alcance de carrera sale del plan de estudios.
- **B. Mantener la carrera del grupo** (ADR-074).

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- Alcance **C** de grupos (`Alcance::seccionesDeCarreras`): los grupos de las materias que están en los planes de su carrera, aunque los compartan otras. Las carreras de un grupo (`Alcance::carrerasDeSeccion`) son todas las que llevan su materia en un plan.
- La **cancelación por carrera** (RF32) alcanza al **grupo entero**: toda clase de una materia del plan de esa carrera, también para los estudiantes de otras carreras.
- `sections.career_id` queda **solo como dato** de la carrera que solicitó el grupo. Al crearlo se sigue eligiendo (filtra las materias), pero no limita a nadie.
- Sigue vigente de ADR-074 que solo el Administrador (G) y el Jefe (D) crean grupos (`secciones.crear`).

## Consecuencias
- El Coordinador vuelve a ver los grupos de materias compartidas, como MAT115.
- El acta de un grupo compartido la puede validar el Coordinador de cualquiera de sus carreras; la primera validación vale.
- Los avisos a coordinadores y los reportes por carrera usan la misma regla.
- Sin migración: la columna se conserva.
