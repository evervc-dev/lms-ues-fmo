---
tipo: prueba
rf: "[[RF16]]"
nombre: Evaluaciones y cuestionarios en línea
prioridad: Media
nivel: browser + feature
casos: 7
estado: automatizado
---

# CP-RF16 · Evaluaciones y cuestionarios en línea

Casos derivados de los criterios de aceptación de [[RF16]]. Nivel: **browser + feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF16-01 | **Dado** un banco de 50 preguntas y un cuestionario de 10, **cuando** cada estudiante lo inicia, **entonces** recibe 10 preguntas al azar. | automatizado |
| CP-RF16-02 | **Dado** el tiempo límite, **cuando** se agota, **entonces** el intento se envía automáticamente con lo guardado y se rechazan respuestas posteriores. | pasa |
| CP-RF16-03 | **Dado** una pérdida de conexión, **cuando** el estudiante vuelve, **entonces** recupera sus respuestas, que se guardan al cambiarlas y cada 30 s ([[RNF10]]). | pasa |
| CP-RF16-04 | **Dado** una pregunta de opción múltiple incorrecta, **cuando** se publica la revisión, **entonces** se muestra la justificación de la respuesta correcta. La revisión se publica sola 10 minutos después del cierre ([[ADR-040 Solicitud de revisión de calificaciones]]). | pasa |
| CP-RF16-05 | **Dado** un cuestionario cerrado, **entonces** el docente ve el porcentaje de aciertos por pregunta. | pasa |
| CP-RF16-06 | **Dado** un intento cuyo tiempo venció sin envío, **entonces** se cierra y se corrige solo una vez ([[ADR-042 Flujo del examen sin depender de Reverb]]). | pasa |
| CP-RF16-07 | **Dado** un cuestionario en curso, **entonces** el docente ve el estado de cada estudiante, cierra intentos y da minutos extra a uno, varios o todos, hasta 30 min después del cierre; la corrección espera al último intento; otro docente, el estudiante o el coordinador reciben 403. | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF16-01 …')`.
