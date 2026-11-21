---
tipo: prueba
rf: "[[RF34]]"
nombre: Validación de actas y cierre departamental
prioridad: Alta
nivel: feature
casos: 13
estado: pasa
---

# CP-RF34 · Validación de actas y cierre departamental

Casos derivados de los criterios de aceptación de [[RF34]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF34-01 | **Dado** una sección con todas sus notas registradas, **cuando** el docente publica el acta, **entonces** el acta pasa a `published` y el docente ya no puede cambiar notas sin que se la devuelvan. | pasa |
| CP-RF34-02 | **Dado** una sección con actividades sin nota, **cuando** el docente publica, **entonces** se le advierte que contarán como 0 y debe confirmar. | pasa |
| CP-RF34-03 | **Dado** un acta publicada, **cuando** el Coordinador la valida, **entonces** pasa a `validated` y queda auditada. | pasa |
| CP-RF34-04 | **Dado** un acta publicada, **cuando** el Coordinador la devuelve con observaciones, **entonces** pasa a `returned`, el docente recibe una notificación y puede corregir y volver a publicar. | pasa |
| CP-RF34-05 | **Dado** un departamento con actas sin validar, **cuando** el Jefe intenta autorizar el cierre, **entonces** se rechaza y se listan las secciones pendientes. | pasa |
| CP-RF34-06 | **Dado** todas las actas del departamento validadas, **cuando** el Jefe autoriza, **entonces** se registra la autorización departamental con fecha y autor. | pasa |
| CP-RF34-07 | **Dado** algún departamento sin autorizar, **cuando** el Administrador intenta cerrar el período, **entonces** se rechaza y se listan los departamentos pendientes. | pasa |
| CP-RF34-08 | **Dado** todos los departamentos autorizados, **cuando** el Administrador cierra, **entonces** se congelan los resultados en `enrollment_results` ([[RF17]]). | pasa |
| CP-RF34-09 | **Dado** un acta validada, **cuando** se necesita cambiar una nota antes del cierre, **entonces** el Coordinador la devuelve y el ciclo se repite; después del cierre aplica la corrección del Coordinador ([[RF17]]). | pasa |
| CP-RF34-10 | **Dado** «Actas por validar» sin actas en el estado elegido, **entonces** la tabla dice que no hay ninguna, en vez de quedarse con el cuerpo en blanco. | pasa |
| CP-RF34-11 | **Dado** un departamento que ya autorizó el cierre, **cuando** el Coordinador devuelve un acta suya, **entonces** la autorización se anula, y el período no se cierra mientras haya actas sin validar. | automatizado |
| CP-RF34-12 | **Dado** notas en borrador o intentos con ensayos por calificar, **cuando** el docente publica el acta, **entonces** se rechaza con el motivo. | automatizado |
| CP-RF34-13 | **Dado** un acta publicada, **entonces** el aviso va solo a los coordinadores de la carrera del grupo (`sections.career_id`, ADR-074), los únicos que pueden validarla. | automatizado |
> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF34-01 …')`.
>
> **27/10/2026:** los casos pasan a `pasa` con la corrida contra **PostgreSQL 17**: 107 pruebas en verde (`vendor/bin/pest --filter="CP-RF34"`), ninguna saltada. Hasta entonces parte de ellos solo existía como prueba escrita, porque se saltaban en SQLite y el repositorio no tenía CI que los repitiera.
