---
tipo: prueba
rf: "[[RF31]]"
nombre: Mensajería condicional a estudiantes
prioridad: Media
nivel: feature
casos: 4
estado: pasa
---

# CP-RF31 · Mensajería condicional a estudiantes

Casos derivados de los criterios de aceptación de [[RF31]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF31-01 | **Dado** la condición «no entregaron», **entonces** la lista incluye solo a los matriculados sin entrega. | pasa |
| CP-RF31-02 | **Dado** la condición «nota menor a 6.0», **entonces** la lista incluye solo a quienes tienen nota publicada menor a 6.0. | pasa |
| CP-RF31-03 | **Dado** el docente, **antes** de enviar, **entonces** ve la lista de destinatarios y puede quitar a alguien. | pasa |
| CP-RF31-04 | **Dado** el envío, **entonces** cada estudiante recibe un correo individual (sin ver a los demás destinatarios) y queda en el historial. | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF31-01 …')`.
