---
tipo: prueba
rf: "[[RF33]]"
nombre: Indicadores de desempeño docente
prioridad: Media
nivel: feature
casos: 5
estado: automatizado
---

# CP-RF33 · Indicadores de desempeño docente

Casos derivados de los criterios de aceptación de [[RF33]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF33-01 | **Dado** el Jefe, **entonces** ve los indicadores de los docentes de su departamento, filtrables por período. | automatizado |
| CP-RF33-02 | **Dado** un docente, **entonces** su puntualidad se calcula como los minutos entre el inicio programado de la clase y la apertura de la sesión de asistencia. | automatizado |
| CP-RF33-03 | **Dado** el tiempo de revisión, **entonces** se mide desde la entrega hasta la publicación de la nota. | automatizado |
| CP-RF33-04 | **Dado** el panel, **entonces** se exporta a Excel o PDF ([[RF25]]). | automatizado |
| CP-RF33-05 | **Dado** el panel de desempeño docente, **cuando** se exporta, **entonces** sale en Excel, PDF y CSV con los indicadores del período elegido ([[ADR-049 Exportación a Excel y PDF]]). | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF33-01 …')`.
