---
tipo: prueba
rf: "[[RF38]]"
nombre: Lección con cuestionario
prioridad: Media
nivel: feature
casos: 8
estado: automatizado
---

# CP-RF38 · Lección con cuestionario

Casos derivados de los criterios de aceptación de [[RF38]]. Nivel: **feature** (`tests/Feature/Contenidos/LeccionTest.php`). Ver [[Estrategia de pruebas]].

| ID | Caso | Estado |
|---|---|---|
| CP-RF38-01 | **Dado** un docente, **cuando** publica la lección en un solo asistente, **entonces** quedan el recurso con la regla `read` y el cuestionario con `required_resource_id`; si el cuestionario se rechaza, no queda nada. | automatizado |
| CP-RF38-02 | **Dado** un estudiante, **cuando** abre la lección, **entonces** ve el material y el cuestionario bloqueado en la misma página. | automatizado |
| CP-RF38-03 | **Dado** menos de los minutos mínimos, **cuando** pulsa «Ya lo leí», **entonces** se rechaza; al cumplirlos, se acepta. | automatizado |
| CP-RF38-04 | **Dado** que se exige llegar al final, **entonces** el tiempo no basta hasta llegar; en un archivo que solo se descarga basta el tiempo. | automatizado |
| CP-RF38-05 | **Dado** la lectura incompleta, **cuando** inicia el cuestionario por el servicio, **entonces** se rechaza; desde otro enlace va a la lección. | automatizado |
| CP-RF38-06 | **Dado** la lectura completa, **cuando** confirma «Intentar cuestionario», **entonces** se abre el intento en modo examen. | automatizado |
| CP-RF38-07 | **Dado** un docente de otra sección o un estudiante no matriculado, **entonces** recibe 403. | automatizado |
| CP-RF38-08 | **Dado** una lección, **cuando** el docente la crea, **entonces** su cuestionario es una verificación de lectura: no pide cómputo ni peso, no admite preguntas de ensayo y el resultado del intento no entra al libro de notas; el estudiante lo ve como retroalimentación ([[ADR-091 Lección con cuestionario y lectura comprobada]]). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF38-01 …')`.
