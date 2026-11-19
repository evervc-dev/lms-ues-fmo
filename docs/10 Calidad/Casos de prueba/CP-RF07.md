---
tipo: prueba
rf: "[[RF07]]"
nombre: Gestión de asignaturas
prioridad: Alta
nivel: feature
casos: 12
estado: automatizado
---

# CP-RF07 · Gestión de asignaturas

Casos derivados de los criterios de aceptación de [[RF07]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF07-01 | **Dado** un código que ya existe, **cuando** se crea una asignatura, **entonces** se rechaza. | automatizado |
| CP-RF07-02 | **Dado** una asignatura con secciones en algún período, **cuando** se intenta eliminar, **entonces** se impide; solo se puede desactivar. | automatizado |
| CP-RF07-03 | **Dado** un prerrequisito, **entonces** no puede crear un ciclo (A requiere B y B requiere A). | automatizado |
| CP-RF07-04 | **Dado** el catálogo, **cuando** se filtra por departamento, créditos o nombre, **entonces** devuelve los resultados en menos de 3 s ([[RNF03]]). | automatizado |
| CP-RF07-05 | **Dado** el catálogo, **cuando** se busca «administracion» sin tilde, **entonces** encuentra «Administración de Centros de Cómputo», en SQLite y en PostgreSQL. | automatizado |
| CP-RF07-06 | **Dado** una asignatura, **cuando** se crea, se edita, se desactiva o se cambian sus prerrequisitos, **entonces** queda en la auditoría con el actor y los valores anterior y nuevo ([[RF05]], [[RNF02]]). | automatizado |
| CP-RF07-07 | **Dado** el formulario de la asignatura en una pantalla táctil, **cuando** se eligen varios prerrequisitos, **entonces** se puede sin la tecla Ctrl. | automatizado |
| CP-RF07-08 | **Dado** un código de departamento que ya existe (sin distinguir mayúsculas), **cuando** se crea un departamento, **entonces** se rechaza; el código se guarda en mayúsculas. | automatizado |
| CP-RF07-09 | **Dado** un departamento, **cuando** se editan su código o su nombre, **entonces** se guardan y quedan en la auditoría; guardar sin cambios no deja rastro. | automatizado |
| CP-RF07-10 | **Dado** un departamento con carreras, asignaturas, docentes, jefaturas (aun cerradas), cierres, plazos de matrícula, fechas del calendario o historial en la auditoría, **cuando** se intenta eliminar, **entonces** se impide con un mensaje que nombra lo que lo usa. | automatizado |
| CP-RF07-11 | **Dado** un departamento que nada usa, **cuando** se elimina, **entonces** desaparece del catálogo. | automatizado |
| CP-RF07-12 | **Dado** el Jefe de Departamento, **cuando** intenta crear, editar o eliminar un departamento (ruta o Service), **entonces** recibe 403; el Administrador sí puede. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF07-01 …')`.
