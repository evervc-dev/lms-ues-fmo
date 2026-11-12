---
tipo: prueba
rf: "[[RF13]]"
nombre: Seguimiento y auditoría de acceso a recursos
prioridad: Media-Alta
nivel: feature
casos: 3
estado: automatizado
---

# CP-RF13 · Seguimiento y auditoría de acceso a recursos

Casos derivados de los criterios de aceptación de [[RF13]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF13-01 | **Dado** un estudiante, **cuando** abre o descarga un recurso, **entonces** se registra con fecha y hora. | automatizado |
| CP-RF13-02 | **Dado** un recurso abierto, **entonces** se mide el tiempo con la pestaña visible. | automatizado |
| CP-RF13-03 | **Dado** el docente, **entonces** ve un mapa de calor de recursos × estudiantes con aperturas y tiempo. | automatizado |

> Pruebas: `tests/Feature/Contenidos/SeguimientoRecursosTest.php` (8). El latido de la página se comprobó una vez en Chromium con Playwright el 01/11/2026: a los 30 s quedaron 30 segundos registrados y sin errores de JavaScript.
