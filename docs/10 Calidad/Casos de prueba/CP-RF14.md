---
tipo: prueba
rf: "[[RF14]]"
nombre: Reutilización de contenido entre períodos
prioridad: Media
nivel: feature
casos: 2
estado: automatizado
---

# CP-RF14 · Reutilización de contenido entre períodos

Casos derivados de los criterios de aceptación de [[RF14]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF14-01 | **Dado** un curso de un período anterior, **cuando** el docente importa unidades, **entonces** se copian con sus recursos, sin entregas ni calificaciones. | automatizado |
| CP-RF14-02 | **Dado** una evaluación importada, **entonces** sus fechas quedan vacías para reprogramarlas. | automatizado |

> Pruebas: `tests/Feature/Contenidos/ReutilizacionTest.php` (CP-RF14-01 con 3 pruebas y CP-RF14-02 con 3, una de ellas solo en PostgreSQL).
