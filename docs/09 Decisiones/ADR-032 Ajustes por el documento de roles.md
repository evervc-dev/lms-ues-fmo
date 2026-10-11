---
tipo: adr
id: ADR-032
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF03]]", "[[RF06]]", "[[RF09]]", "[[RF15]]", "[[RF17]]", "[[RF20]]", "[[RF29]]", "[[RF34]]", "[[Matriz RBAC]]", "[[Modelo entidad-relación]]", "[[ADR-030 Matrícula ampliada]]", "[[ADR-006 Rúbricas y calificación anónima]]"]
fuentes: ["Roles y permisos.pdf (revisión del 10/10/2026)"]
---

# ADR-032 · Ajustes tras revisar el documento de roles

> **17/10/2026:** el punto 2 («Soporte no matricula») y la parte del punto 6 sobre el Jefe y los docentes quedan reemplazados por [[ADR-037 Jerarquía de creación y asignación de roles]] (aceptada).

## Contexto
Al contrastar el vault con *Roles y permisos.pdf* aparecieron dos funciones de enfoque sin cubrir, una contradicción con ADR-030 y varias mejoras.

## Decisión
*Decidido por el equipo el 10/10/2026:*

| # | Decisión | Antes |
|---|---|---|
| 1 | **Flujo de actas** ([[RF34]]): el docente publica las notas finales de la sección → el **Coordinador valida** el acta → el **Jefe autoriza el cierre** de su departamento → el Administrador cierra el período cuando todos los departamentos autorizaron | El Administrador cerraba sin validación ni autorización |
| 2 | **Soporte no matricula.** Respeta "sin privilegios académicos" del documento de roles | [[ADR-030 Matrícula ampliada]] le permitía matricular en cualquier sección |
| 3 | **Diagnóstico QR para Soporte**, de solo lectura: los intentos de marcación y su motivo exacto | Solo los veía el docente |
| 4 | **Calificación anónima dentro del alcance**: `assessments.is_anonymous` oculta los nombres hasta publicar | Opcional, fase posterior ([[ADR-006 Rúbricas y calificación anónima]]) |
| 5 | **Boleta por unidad**: `grade_items.unit_id` opcional; la boleta se ve por cómputo y por unidad | Solo por cómputo |
| 6 | **Puntos abiertos de la matriz cerrados**: el Administrador **no** ve contenidos ni notas, y el Jefe **no** asigna docentes | Por confirmar |
| 7 | **Roles seguros**: el Administrador asigna permisos a roles desde la interfaz, pero no crea permisos nuevos, y no se puede quitar `roles.gestionar` al último Administrador | Sin regla |
| 8 | **Clave de matrícula por asignatura** (materia + período): con la clave, el estudiante elige una sección con cupo y sin choque de horario | Clave por sección |

## Consecuencias
- Nuevo RF34, asignado al Equipo 4 en H3, con su Feature en OpenProject.
- Modelo: `grade_sheets`, `department_closures`, `assessments.is_anonymous`, `grade_items.unit_id`, y `enrollment_keys` por `subject_id` + `period_id`.
- La Matriz RBAC agrega la tabla de equivalencias con el documento de roles.
