---
tipo: prueba
rf: "[[RF07]]"
nombre: Gestión de asignaturas
prioridad: Alta
nivel: feature
casos: 24
estado: automatizado
---

# CP-RF07 · Gestión de asignaturas

Casos derivados de los criterios de aceptación de [[RF07]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF07-01 | **Dado** un código que ya existe, **cuando** se crea una asignatura, **entonces** se rechaza. | automatizado |
| CP-RF07-02 | **Dado** una asignatura con secciones en algún período, **cuando** se intenta eliminar, **entonces** se impide; solo se puede archivar, y no mientras tenga grupos en un período que no está cerrado ([[ADR-092 Archivar períodos, departamentos, carreras y asignaturas]]). | automatizado |
| CP-RF07-03 | **Dado** un prerrequisito, **entonces** no puede crear un ciclo (A requiere B y B requiere A). | automatizado |
| CP-RF07-04 | **Dado** el catálogo, **cuando** se filtra por carrera (solo las de su alcance: todas para el Administrador, las de su departamento para el Jefe), créditos o nombre, **entonces** devuelve los resultados en menos de 3 s ([[RNF03]]). | automatizado |
| CP-RF07-05 | **Dado** el catálogo, **cuando** se busca «administracion» sin tilde, **entonces** encuentra «Administración de Centros de Cómputo», en SQLite y en PostgreSQL. | automatizado |
| CP-RF07-06 | **Dado** una asignatura, **cuando** se crea, se edita, se desactiva o se cambian sus prerrequisitos, **entonces** queda en la auditoría con el actor y los valores anterior y nuevo ([[RF05]], [[RNF02]]). | automatizado |
| CP-RF07-07 | **Dado** el formulario de la asignatura en una pantalla táctil, **cuando** se eligen varios prerrequisitos, **entonces** se puede sin la tecla Ctrl. | automatizado |
| CP-RF07-08 | **Dado** un código de departamento que ya existe (sin distinguir mayúsculas), **cuando** se crea un departamento, **entonces** se rechaza; el código se guarda en mayúsculas. | automatizado |
| CP-RF07-09 | **Dado** un departamento, **cuando** se editan su código o su nombre, **entonces** se guardan y quedan en la auditoría; guardar sin cambios no deja rastro. | automatizado |
| CP-RF07-10 | **Dado** un departamento con carreras, asignaturas, docentes, jefaturas (aun cerradas), cierres, plazos de matrícula, fechas del calendario o historial en la auditoría, **cuando** se intenta eliminar, **entonces** se impide con un mensaje que nombra lo que lo usa. | automatizado |
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
| CP-RF07-21 | **Dado** el Administrador o el Jefe, **cuando** crea o edita una asignatura, **entonces** lo hace en su propia página (sus carreras y planes crecen con los datos); el Jefe no abre la de otro departamento (403). | automatizado |
| CP-RF07-22 | **Dado** el Administrador, **cuando** abre Departamentos, **entonces** ve cada departamento con su jefe, carreras, asignaturas y grupos del período; al elegir uno ve sus carreras y entra al detalle de cualquiera. Desde esa misma pantalla crea, edita y elimina departamentos (las reglas de CP-RF07-08 a 11). El Jefe y el Coordinador no entran a Departamentos y el filtro por departamento no amplía su alcance ([[ADR-083 El Administrador navega departamentos y carreras]]). | automatizado |
| CP-RF07-23 | **Dado** PR1 ← PR2 ← PR3 por prerrequisito, **cuando** se desactiva PR1, **entonces** las tres quedan inactivas y la confirmación y el aviso nombran PR2 y PR3; al reactivar PR1 solo vuelve ella. Si una dependiente activa es de otro departamento, el Jefe no puede desactivar (el Administrador sí). | automatizado |
| CP-RF07-24 | **Dado** el Administrador, **cuando** crea una carrera en un departamento con su primer plan, o agrega un plan a una carrera, **entonces** quedan creados y auditados; un código o un nombre de plan repetido se rechaza. El Jefe y el Coordinador no pueden (403). | automatizado |
| CP-RF07-25 | **Dado** una asignatura en un plan de estudios, **cuando** se eligen sus prerrequisitos, **entonces** solo se ofrecen las materias de ese plan, y guardar uno de otro pensum se rechaza nombrándolo. | automatizado |
| CP-RF07-26 | **Dado** el Administrador (o el Jefe con una materia de su departamento) en el detalle de una carrera, **cuando** pulsa una materia, **entonces** abre su ficha en el Catálogo, que muestra los grupos del período con su docente, horario e inscritos. Quien no gestiona el catálogo no ve el enlace. | automatizado |
| CP-RF07-27 | **Dado** una asignatura, **cuando** se intenta ponerla en dos planes de la misma carrera, **entonces** se rechaza; en planes de carreras distintas sí se permite ([[ADR-089 Una asignatura va en un solo plan de cada carrera]]). | automatizado |
| CP-RF07-28 | **Dado** un departamento, **cuando** el Administrador lo archiva, **entonces** se rechaza si tiene carreras sin archivar, asignaturas activas o jefatura vigente; archivado, sale de las listas y los selectores y no admite carreras ni asignaturas nuevas ([[ADR-092 Archivar períodos, departamentos, carreras y asignaturas]]). | automatizado |
| CP-RF07-29 | **Dado** una carrera, **cuando** el Administrador la archiva, **entonces** se rechaza si tiene estudiantes activos o coordinación vigente; archivada, sale de las listas y los selectores, no admite planes ni asignaturas y no se desarchiva con su departamento archivado ([[ADR-092 Archivar períodos, departamentos, carreras y asignaturas]]). | automatizado |
| CP-RF07-30 | **Dado** un Jefe, Coordinador, Docente o Estudiante, **cuando** intenta archivar un período, un departamento o una carrera, **entonces** recibe 403 ([[ADR-092 Archivar períodos, departamentos, carreras y asignaturas]]). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF07-01 …')`.
