---
tipo: prueba
rf: "[[RF25]]"
nombre: Reportes y exportación de datos
prioridad: Media
nivel: feature
casos: 8
estado: automatizado
---

# CP-RF25 · Reportes y exportación de datos

Casos derivados de los criterios de aceptación de [[RF25]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF25-01 | **Dado** un docente, **cuando** genera un reporte, **entonces** solo incluye sus secciones. | automatizado |
| CP-RF25-02 | **Dado** un reporte programado, **entonces** se genera en segundo plano y se envía por correo. | automatizado |
| CP-RF25-03 | **Dado** un reporte, **cuando** se exporta, **entonces** sale en Excel, PDF y CSV, respetando el alcance de quien lo pide ([[ADR-049 Exportación a Excel y PDF]]). | pasa |
| CP-RF25-04 | **Dado** cualquier reporte, **cuando** muestra una fecha, **entonces** la escribe como DD/MM/AAAA ([[RNF11]]). | automatizado |
| CP-RF25-05 | **Dado** un envío programado, **cuando** se programa otra vez el mismo reporte con la misma frecuencia, **entonces** se rechaza o se advierte, sin crear el duplicado ([[RNF12]]). | automatizado |
| CP-RF25-06 | **Dado** un reporte, **entonces** cada fila dice su carrera: la de los planes de la asignatura en los reportes por sección (todas, si está en varios) y la del estudiante en «Estudiantes en riesgo»; **cuando** se filtra por carrera, **entonces** solo valen las de su alcance (QA del rol Jefe, 31/10/2026). | automatizado |
| CP-RF25-07 | **Dado** un período cerrado, **cuando** se elige en el selector, **entonces** el reporte y su exportación muestran ese período; un período no consultable vuelve al en curso (R-12). | automatizado |
| CP-RF25-08 | **Dado** un reporte con datos, **entonces** muestra un gráfico de su medida principal sin las filas sin dato, y la tabla sigue con todas (R-11). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF25-01 …')`.
