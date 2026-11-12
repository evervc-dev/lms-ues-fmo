---
tipo: prueba
rf: "[[RF10]]"
nombre: Estadísticas de oferta académica
prioridad: Media
nivel: feature
casos: 2
estado: pendiente
---

# CP-RF10 · Estadísticas de oferta académica

Casos derivados de los criterios de aceptación de [[RF10]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF10-01 | **Dado** un período cerrado, **entonces** muestra por asignatura los ingresados, egresados, aprobados y reprobados. | pendiente |
| CP-RF10-02 | **Dado** el histórico, **entonces** propone el número máximo de grupos para el siguiente período, que la jefatura puede ajustar. | pendiente |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF10-01 …')`.
