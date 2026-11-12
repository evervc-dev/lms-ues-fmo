---
tipo: prueba
rf: "[[RF08]]"
nombre: Gestión de grupos o secciones
prioridad: Alta
nivel: feature
casos: 6
estado: automatizado
---

# CP-RF08 · Gestión de grupos o secciones

Casos derivados de los criterios de aceptación de [[RF08]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF08-01 | **Dado** un docente o un aula ya ocupados en ese día y hora, **cuando** se guarda la sección, **entonces** se rechaza indicando el conflicto. | automatizado |
| CP-RF08-02 | **Dado** una sección, **entonces** puede tener más de un docente. | automatizado |
| CP-RF08-03 | **Dado** un cambio de horario o aula, **entonces** se notifica a los estudiantes y docentes involucrados ([[RF23]]). | automatizado |
| CP-RF08-04 | **Dado** un Jefe, **cuando** asigna un docente a una sección de una materia de su departamento, **entonces** se asigna; en otro departamento recibe 403. No crea secciones ni horarios ([[ADR-037 Jerarquía de creación y asignación de roles]]). | pasa |
| CP-RF08-05 | **Dado** un Jefe, **cuando** cambia el cupo de una sección de su departamento, **entonces** se guarda; no por debajo de los inscritos activos ni del cupo mínimo, ni en otro departamento. El Coordinador no cambia el cupo. | pasa |
| CP-RF08-06 | **Dado** los topes de `system_settings`, **cuando** se asigna un tercer docente a una sección o se pasa de 10 horas semanales, **entonces** se rechaza nombrando el tope y el valor actual ([[ADR-054 Topes de la carga académica]]). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF08-01 …')`.
