---
tipo: prueba
rf: "[[RF24]]"
nombre: Panel principal por rol
prioridad: Media-Alta
nivel: feature
casos: 3
estado: pendiente
---

# CP-RF24 · Panel principal por rol

Casos derivados de los criterios de aceptación de [[RF24]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF24-01 | **Dado** un estudiante, **entonces** su panel muestra primero la lista de pendientes ordenada por fecha y el calendario. | pasa |
| CP-RF24-02 | **Dado** cualquier panel, **entonces** carga en menos de 3 s ([[RNF03]]). | pendiente |
| CP-RF24-03 | **Dado** el docente, **entonces** ve una bandeja de pendientes de calificar: tareas y preguntas de ensayo. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF24-01 …')`.
