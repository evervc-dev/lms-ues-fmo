---
tipo: prueba
rf: "[[RF05]]"
nombre: Auditoría y trazabilidad de acciones
prioridad: Alta
nivel: feature
casos: 8
estado: automatizado
---

# CP-RF05 · Auditoría y trazabilidad de acciones

Casos derivados de los criterios de aceptación de [[RF05]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF05-01 | **Dado** un cambio de nota, corrección de asistencia, modificación de período, asignación docente o gestión de usuarios, **entonces** se crea un registro con usuario, fecha, hora, IP, acción, entidad, valor anterior y valor nuevo. | automatizado |
| CP-RF05-02 | **Dado** el Administrador, **cuando** consulta la auditoría, **entonces** ve todos los eventos y puede filtrar por usuario, fecha, tipo de acción y entidad, y exportar. | pasa |
| CP-RF05-03 | **Dado** el Jefe de Departamento, **cuando** consulta la auditoría, **entonces** solo ve los eventos de su departamento, aunque intente filtrar por otro (RLS). | automatizado |
| CP-RF05-04 | **Dado** cualquier otro rol, **cuando** intenta abrir la auditoría, **entonces** se rechaza. | automatizado |
| CP-RF05-05 | **Dado** un registro de auditoría, **entonces** nadie puede editarlo ni eliminarlo, tampoco el Administrador ([[RNF02]]). | automatizado |
| CP-RF05-06 | **Dado** la pantalla de Auditoría, **entonces** la consulta no cuenta las filas para paginar (`simplePaginate`) y la lista de entidades del filtro no se vuelve a calcular en cada render. | pasa |
| CP-RF05-07 | **Dado** la tabla `audit_logs`, **entonces** existe un índice por `occurred_at`, que es el orden con el que la lee la pantalla. | pasa |
| CP-RF05-08 | **Dado** la auditoría, **cuando** se exporta, **entonces** sale en Excel y CSV; **no** se ofrece PDF, porque la exportación llega a 10 000 filas y dompdf las armaría todas en memoria ([[ADR-049 Exportación a Excel y PDF]]). | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF05-01 …')`.
>
> **27/10/2026:** CP-RF05-02 asserta los cuatro filtros (usuario, fecha, acción y entidad) y la exportación en `tests/Feature/Auditoria/AuditoriaTest.php`. Solo corre en SQLite: en PostgreSQL los eventos los escriben los triggers y `escenario()` ya deja los suyos, así que no se puede fijar el conjunto esperado.
