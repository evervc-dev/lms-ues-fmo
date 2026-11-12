---
tipo: prueba
rf: "[[RF32]]"
nombre: Clases canceladas y cambios de modalidad
prioridad: Media
nivel: feature
casos: 3
estado: automatizado
---

# CP-RF32 · Clases canceladas y cambios de modalidad

Casos derivados de los criterios de aceptación de [[RF32]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF32-01 | **Dado** un día cancelado, **entonces** las sesiones de ese día no cuentan en el porcentaje de asistencia ([[RF22]]) y se notifica a los afectados. | automatizado |
| CP-RF32-02 | **Dado** un cambio de modalidad, **entonces** la sesión usa el método de asistencia de la nueva modalidad: QR ([[RF20]]) o reporte de Meet ([[RF21]]). | automatizado |
| CP-RF32-03 | **Dado** cualquier cancelación o cambio, **entonces** queda en la auditoría con su motivo. | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF32-01 …')`.
