---
tipo: adr
id: ADR-073
estado: pendiente
fecha: 2026-11-18
afecta: ["[[RF08]]", "[[Matriz RBAC]]", "[[Jefe de Departamento]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]", "[[ADR-072 El Jefe gestiona las asignaturas de su departamento]]"]
fuentes: ["Decisión del usuario del 18/11/2026"]
---

# ADR-073 · El Jefe crea los grupos teóricos de su departamento

> [!info] Pendiente de aceptar por el equipo. Enmienda [[ADR-037 Jerarquía de creación y asignación de roles]], que decía que el Jefe «no crea secciones ni horarios».

## Contexto
Con [[ADR-072 El Jefe gestiona las asignaturas de su departamento]] el Jefe ya crea las materias de su departamento, pero para abrirlas en un período dependía del Administrador o del Coordinador, porque solo ellos tenían `secciones.gestionar`. El usuario pidió que el Jefe cree los grupos teóricos (secciones) de las materias de su departamento y defina o actualice su capacidad.

## Opciones
- **A. Jefe con `secciones.gestionar` D:** crea el grupo con su cupo y arma su horario y aula, como el Coordinador en su carrera.
- **B. Un permiso nuevo solo para crear y cupo:** el horario y el aula los siguen poniendo el Administrador o el Coordinador; obliga a partir el permiso y a coordinar a dos personas para un mismo grupo.

## Decisión
**A.** *Decidida por el usuario el 18/11/2026.*
- `secciones.gestionar`: Administrador G, **Jefe D**, Coordinador C.
- Con D, el Jefe crea grupos solo de las materias de su departamento (`subjects.department_id`), con su cupo, y les agrega o quita bloques de horario y cambia el aula, con los mismos controles de choque de aula, de horario del docente y de tope de horas ([[ADR-054 Topes de la carga académica]]). El cupo lo sigue cambiando con `secciones.cupo` D.
- En otro departamento recibe 403.

## Consecuencias
- [[RF08]] suma al Jefe en sus roles; CP-RF08-04 se reescribe y se agregan CP-RF08-07 y CP-RF08-08.
- En la interfaz, «sección» se muestra como **grupo teórico** (y «Grupo» donde no hay espacio). El código, la tabla `sections`, las URL y los permisos `secciones.*` no cambian.
