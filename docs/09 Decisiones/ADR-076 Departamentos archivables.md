---
tipo: adr
id: ADR-076
estado: pendiente
fecha: 2026-11-19
afecta: ["[[RF07]]", "[[RF29]]", "[[RF34]]", "[[ADR-031 Modelo de datos v2 en inglés]]", "[[ADR-034 Navegación y layouts]]", "[[Modelo entidad-relación]]", "[[Administrador]]"]
fuentes: ["Decisión del usuario del 19/11/2026"]
---

# ADR-076 · Departamentos archivables

> [!info] Pendiente de aceptar por el equipo. Amplía el catálogo que el Administrador mantiene según [[ADR-034 Navegación y layouts]].

## Contexto
El Administrador crea, edita y elimina departamentos en Catálogo › Estructura (RF07, CP-RF07-08 a 11), pero solo puede eliminar los que nada usa; hasta ahora no había forma de retirar uno con historial (la Bitácora del 18/11 decía «los departamentos no se desactivan»). [[ADR-031 Modelo de datos v2 en inglés]] limita el borrado lógico (`deleted_at`) a `units` y `resources`, y `departments` no tenía columna de estado: un departamento con carreras, asignaturas o historial no se podía retirar sin romper las FK `RESTRICT`.

## Opciones
- **A. `departments.is_active`**, como `subjects.is_active`. Lo que cuelga del departamento (carreras y asignaturas) se oculta por herencia, sin escribir en esas tablas.
- **B. `is_active` en `departments` y en `careers`:** también se archivan carreras por separado. Nadie lo pidió.
- **C. `archived_at`:** guarda la fecha; la auditoría ya la registra.

## Decisión
**A.** *Decidida por el usuario el 19/11/2026.*
- **Archivar** se impide si el departamento tiene secciones en un período **sin cerrar** (en curso o planificado): así nunca queda una clase en curso en un departamento archivado ni se altera el cierre ([[RF34]]). Se puede **reactivar**.
- **Eliminar** borra la fila solo si nada la usa (carreras, asignaturas, docentes, jefes); si no, se ofrece archivar, igual que con las asignaturas.
- Un departamento archivado **desaparece de las listas de trabajo**: sus asignaturas no se ofrecen en la inscripción ni al crear secciones, y el departamento no aparece al asignar Jefe o Docente, en los plazos de matrícula ([[RF29]]) ni en el total del panel y del cierre. El historial de períodos cerrados (boletas, expedientes, auditoría) se conserva.
- Lo gestiona el Administrador con `asignaturas.gestionar` (G), el mismo permiso del catálogo, en Catálogo › Estructura junto al alta, la edición y el borrado (`CatalogoService`). El Jefe tiene ese permiso con alcance D ([[ADR-072 El Jefe gestiona las asignaturas de su departamento]]) y no archiva.

## Consecuencias
- Migración: `departments.is_active boolean default true`. El [[Modelo entidad-relación]] lo documenta.
- El trigger de auditoría del catálogo ya registra el cambio (UPDATE) con el actor.
- Nuevos casos CP-RF07-21 a CP-RF07-24; CP-RF07-10 suma que el mensaje ofrece archivar.
- El filtro también aplica a las listas de [[ADR-073 El Jefe crea los grupos teóricos de su departamento]] y [[ADR-074 El grupo teórico pertenece a una carrera y solo lo crean el Administrador y el Jefe]]: departamentos, carreras y materias para crear un grupo teórico, y las carreras del formulario de la asignatura.
