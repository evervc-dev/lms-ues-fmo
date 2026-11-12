---
tipo: prueba
rf: "[[RF09]]"
nombre: Gestión de matrícula en grupos
prioridad: Media-Alta
nivel: feature
casos: 14
estado: automatizado
---

# CP-RF09 · Gestión de matrícula en grupos

Casos derivados de los criterios de aceptación de [[RF09]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF09-01 | **Dado** un grupo lleno, **cuando** se intenta matricular, **entonces** se rechaza. | automatizado |
| CP-RF09-02 | **Dado** una sección cuyo horario se solapa con otra matrícula activa del estudiante, **cuando** se intenta matricular, **entonces** se rechaza con «La sección choca en horario con otra matrícula». | automatizado |
| CP-RF09-03 | ~~**Dado** un estudiante sin un prerrequisito aprobado, **cuando** se matricula, **entonces** se permite (no se validan).~~ Sustituido por CP-RF09-13 ([[ADR-052 Prerrequisitos exigidos al matricular]]). | sustituido |
| CP-RF09-04 | **Dado** el docente o el jefe, **cuando** inscribe, **entonces** solo puede hacerlo en su ámbito: sus secciones o su departamento. | automatizado |
| CP-RF09-05 | ~~**Dado** Soporte Técnico, **cuando** intenta inscribir, **entonces** se rechaza: no tiene privilegios académicos.~~ Sustituido por CP-RF09-11 ([[ADR-037 Jerarquía de creación y asignación de roles]]: Soporte inscribe en cualquier sección, y la [[Matriz RBAC]] le da `matricula.gestionar` con alcance G). | sustituido |
| CP-RF09-06 | **Dado** un estudiante ya matriculado en la misma materia y período, **entonces** se rechaza. | automatizado |
| CP-RF09-07 | **Dado** un estudiante, **cuando** solicita un cambio de grupo con justificación dentro de las **3 primeras semanas**, **entonces** la solicitud va al Coordinador; fuera de plazo se rechaza. | pasa |
| CP-RF09-08 | **Dado** el Coordinador, **cuando** aprueba el cambio, **entonces** el estudiante pasa a la nueva sección conservando entregas y notas, y queda en el historial y en la auditoría. Las notas se heredan **por cómputo** leyendo la cadena, y la asistencia no se encadena ([[ADR-062 Notas y entregas en un cambio de grupo]]). | pasa |
| CP-RF09-09 | **Dado** dos inscripciones simultáneas al último cupo, **entonces** solo una se acepta. | automatizado |
| CP-RF09-10 | **Dado** una matrícula o un cambio, **entonces** el estudiante recibe una notificación ([[RF23]]). | automatizado |
| CP-RF09-11 | **Dado** Soporte, **cuando** inscribe a un estudiante en cualquier sección, **entonces** la matrícula se crea con su `enrolled_by` ([[ADR-037 Jerarquía de creación y asignación de roles]]). | pasa |
| CP-RF09-12 | **Dado** una matrícula activa en la lista, **cuando** el Coordinador la retira con su motivo, **entonces** la fila pasa a «Retirada» en la misma pantalla, sin recargar. | pasa |
| CP-RF09-13 | **Dado** una asignatura con prerrequisitos, **cuando** se matricula a un estudiante que no los tiene aprobados, **entonces** se rechaza nombrando los que faltan, por cualquiera de las vías de matrícula y sin excepción por rol ([[ADR-052 Prerrequisitos exigidos al matricular]]). | automatizado |
| CP-RF09-14 | **Dado** un estudiante de una carrera, **cuando** se le matricula, **entonces** solo se ofrecen y se aceptan las asignaturas del plan de su carrera; una de otra carrera se rechaza aunque se fuerce la petición. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF09-01 …')`.
