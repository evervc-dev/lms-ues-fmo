---
tipo: prueba
rf: "[[RF18]]"
nombre: Seguimiento de progreso por unidad
prioridad: Alta
nivel: feature
casos: 3
estado: automatizado
---

# CP-RF18 · Seguimiento de progreso por unidad

Casos derivados de los criterios de aceptación de [[RF18]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF18-01 | **Dado** un recurso con finalización manual, **cuando** el estudiante marca «ya lo leí», **entonces** cuenta como completado. | automatizado |
| CP-RF18-02 | **Dado** un recurso con finalización automática, **entonces** solo cuenta como completado cuando se cumple la regla (p. ej. nota mínima en un cuestionario o dos mensajes en el foro). | automatizado |
| CP-RF18-03 | **Dado** una unidad, **entonces** muestra una barra con el porcentaje completado. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF18-01 …')`.
