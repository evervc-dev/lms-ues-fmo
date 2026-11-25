---
tipo: prueba
rf: "[[RF07]]"
nombre: Gestión de asignaturas
prioridad: Alta
nivel: feature
casos: 26
estado: automatizado
---

# CP-RF07 · Gestión de asignaturas

Casos derivados de los criterios de aceptación de [[RF07]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF07-01 | **Dado** un código que ya existe, **cuando** se crea una asignatura, **entonces** se rechaza. | automatizado |
| CP-RF07-02 | **Dado** una asignatura con secciones en algún período, **cuando** se intenta eliminar, **entonces** se impide; solo se puede desactivar. | automatizado |
| CP-RF07-03 | **Dado** un prerrequisito, **entonces** no puede crear un ciclo (A requiere B y B requiere A). | automatizado |
| CP-RF07-04 | **Dado** el catálogo, **cuando** se filtra por carrera (solo las de su alcance: todas para el Administrador, las de su departamento para el Jefe), créditos o nombre, **entonces** devuelve los resultados en menos de 3 s ([[RNF03]]). | automatizado |
| CP-RF07-05 | **Dado** el catálogo, **cuando** se busca «administracion» sin tilde, **entonces** encuentra «Administración de Centros de Cómputo», en SQLite y en PostgreSQL. | automatizado |
| CP-RF07-06 | **Dado** una asignatura, **cuando** se crea, se edita, se desactiva o se cambian sus prerrequisitos, **entonces** queda en la auditoría con el actor y los valores anterior y nuevo ([[RF05]], [[RNF02]]). | automatizado |
| CP-RF07-07 | **Dado** el formulario de la asignatura en una pantalla táctil, **cuando** se eligen varios prerrequisitos, **entonces** se puede sin la tecla Ctrl. | automatizado |
| CP-RF07-08 | **Dado** un código de departamento que ya existe (sin distinguir mayúsculas), **cuando** se crea un departamento, **entonces** se rechaza; el código se guarda en mayúsculas. | automatizado |
| CP-RF07-09 | **Dado** un departamento, **cuando** se editan su código o su nombre, **entonces** se guardan y quedan en la auditoría; guardar sin cambios no deja rastro. | automatizado |
| CP-RF07-10 | **Dado** un departamento con carreras, asignaturas, docentes, jefaturas (aun cerradas), cierres, plazos de matrícula, fechas del calendario o historial en la auditoría, **cuando** se intenta eliminar, **entonces** se impide con un mensaje que nombra lo que lo usa y ofrece archivarlo ([[ADR-076 Departamentos archivables]]). | automatizado |
| CP-RF07-11 | **Dado** un departamento que nada usa, **cuando** se elimina, **entonces** desaparece del catálogo. | automatizado |
| CP-RF07-12 | **Dado** el Jefe de Departamento, **cuando** intenta crear, editar o eliminar un departamento (ruta o Service), **entonces** recibe 403; el Administrador sí puede. | automatizado |
| CP-RF07-13 | **Dado** el Jefe de Departamento, **cuando** abre el menú y el Catálogo, **entonces** ve la opción y solo las asignaturas de su departamento ([[ADR-072 El Jefe gestiona las asignaturas de su departamento]]). | automatizado |
| CP-RF07-14 | **Dado** el Jefe, **cuando** crea, edita, desactiva o elimina una asignatura de su departamento, **entonces** puede; sobre otro departamento, o al mover la suya a otro, recibe 403. | automatizado |
| CP-RF07-15 | **Dado** el Jefe, **cuando** intenta entrar a Estructura o crear aulas o departamentos, **entonces** recibe 403 y no ve el enlace; el Administrador sí. | automatizado |
| CP-RF07-16 | **Dado** el Jefe, **cuando** edita la descripción de una asignatura de su departamento, **entonces** se guarda; la de otro departamento se rechaza. | automatizado |
| CP-RF07-17 | **Dado** el formulario de una asignatura nueva, **cuando** se guarda sin carrera ni plan de estudios, **entonces** se rechaza. | automatizado |
| CP-RF07-18 | **Dado** el Jefe, **cuando** crea una asignatura, **entonces** no elige departamento (se usa el suyo), solo ve las carreras de su departamento y la asignatura queda en los planes y ciclos elegidos. | automatizado |
| CP-RF07-19 | **Dado** el plan de una carrera de otro departamento, **cuando** el Jefe lo usa, **entonces** recibe 403; el Administrador sí puede. | automatizado |
| CP-RF07-20 | **Dado** una asignatura en planes de varios departamentos, **cuando** el Jefe la edita, **entonces** solo ve y cambia los de su departamento, y los demás se conservan. | automatizado |
| CP-RF07-21 | **Dado** un departamento sin grupos teóricos en períodos sin cerrar, **cuando** el Administrador lo archiva o lo reactiva, **entonces** cambia `is_active` y queda en la auditoría con el actor. | automatizado |
| CP-RF07-22 | **Dado** un departamento con grupos teóricos en un período sin cerrar (en curso o planificado), **cuando** se intenta archivar, **entonces** se impide. | pasa |
| CP-RF07-23 | **Dado** un departamento archivado, **entonces** sus asignaturas no aparecen en la inscripción del estudiante, la oferta ni al crear grupos teóricos. | pasa |
| CP-RF07-24 | **Dado** un departamento archivado, **entonces** no aparece al asignar Jefe o Docente, en los plazos de matrícula ni en el total de departamentos del panel y del cierre ([[RF34]]). | pasa |
| CP-RF07-25 | **Dado** el Administrador en el panel académico, **cuando** elige un departamento, una carrera y una materia, **entonces** ve sus carreras, las materias del plan y los grupos teóricos del período con enlace a la Vista de sección; otro rol recibe 403. | pasa |
| CP-RF07-26 | **Dado** el Administrador, **cuando** entra a la Vista de sección, **entonces** ve Contenidos, Calificaciones, Asistencia y Progreso en solo lectura y toda escritura sobre la sección (contenido, notas, asistencia, progreso) le devuelve 403; la descripción de la asignatura sigue siendo del catálogo que él mantiene ([[ADR-050 Inscripción por catálogo y claves compartidas]]) ([[ADR-075 El Administrador ve las secciones en solo lectura]]). | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF07-01 …')`.
