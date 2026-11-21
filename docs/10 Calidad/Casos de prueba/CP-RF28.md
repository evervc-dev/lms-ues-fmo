---
tipo: prueba
rf: "[[RF28]]"
nombre: Registro de asistencia mediante código temporal
prioridad: Alta
nivel: feature
casos: 5
estado: automatizado
---

# CP-RF28 · Registro de asistencia mediante código temporal

Casos derivados de los criterios de aceptación de [[RF28]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF28-01 | **Dado** un código con más de 30 s, **entonces** se rechaza. | pasa |
| CP-RF28-02 | **Dado** un código usado por otra cuenta, **entonces** se rechaza. | automatizado |
| CP-RF28-03 | **Dado** un código ya usado, **entonces** no se puede reutilizar. | pasa |
| CP-RF28-04 | **Dado** un estudiante con asistencia ya registrada en la sesión, **entonces** no se genera el código. | automatizado |
| CP-RF28-05 | **Dado** un código temporal, **entonces** solo se emite para una matrícula activa del grupo, y usarlo cuando el estudiante ya tiene registro avisa en vez de fallar. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF28-01 …')`.
