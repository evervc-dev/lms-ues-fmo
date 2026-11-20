---
tipo: adr
id: ADR-075
estado: pendiente
fecha: 2026-11-19
afecta: ["[[RF07]]", "[[ADR-032 Ajustes por el documento de roles]]", "[[ADR-034 Navegación y layouts]]", "[[Matriz RBAC]]", "[[Vista de sección]]", "[[Administrador]]"]
fuentes: ["Decisión del usuario del 19/11/2026"]
---

# ADR-075 · El Administrador ve las secciones en solo lectura

> [!info] Pendiente de aceptar por el equipo. Reemplaza la parte del punto 6 de [[ADR-032 Ajustes por el documento de roles]] que decía «el Administrador **no** ve contenidos ni notas».

## Contexto
El Administrador pidió un panel para recorrer la estructura académica (departamento → carreras → materias → secciones) y, al llegar a una sección, ver lo mismo que el docente: contenido, estudiantes, notas y asistencia. Hasta ahora [[ADR-032 Ajustes por el documento de roles]] (punto 6), la [[Matriz RBAC]] y la [[Vista de sección]] se lo prohibían: el Administrador solo gestionaba las secciones desde **Carga académica** y **Matrícula**, y la Vista de sección le devolvía 403.

## Opciones
- **A. Solo lectura con alcance G:** el Administrador recibe los mismos permisos de lectura que el Coordinador (`contenidos.ver`, `calificaciones.ver`, `asistencia.ver`, `progreso.ver`), con alcance global, y ningún `*.gestionar`.
- **B. Lectura y edición:** además edita contenido y calificaciones como el docente. Confunde la autoría en la auditoría y salta el flujo de actas de [[RF34]].
- **C. Mantener la prohibición:** el panel termina en Estudiantes y Participantes, que ya tiene con G.

## Decisión
**A.** *Decidida por el usuario el 19/11/2026.* El Administrador entra a la Vista de sección de cualquier sección y ve sus pestañas de lectura; toda escritura (publicar contenido, calificar, marcar asistencia, moderar el foro) sigue exigiendo los permisos `*.gestionar` que no tiene, y el Service responde 403.

## Consecuencias
- `database/seeders/data/rbac.php` y la [[Matriz RBAC]] dan `G` al Administrador en `contenidos.ver`, `calificaciones.ver`, `asistencia.ver` y `progreso.ver`. La nota de la Matriz que decía que el Administrador no ve contenidos ni notas se corrige.
- La [[Vista de sección]] deja de decir que el Administrador no entra.
- El Administrador ve el libro de notas igual que el Coordinador; la calificación anónima (`assessments.is_anonymous`) se aplica en Entregas, a la que no llega porque exige `calificaciones.gestionar`.
- Editar la descripción de la asignatura desde la sección sigue permitido: es del catálogo que él mantiene ([[ADR-050 Inscripción por catálogo y claves compartidas]]).
- Cada acción de escritura de la Vista de sección debe exigir un permiso distinto de `*.ver`; lo cubre CP-RF07-26.
