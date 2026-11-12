---
tipo: prueba
rf: "[[RF30]]"
nombre: Foros de discusión
prioridad: Media
nivel: feature
casos: 4
estado: automatizado
---

# CP-RF30 · Foros de discusión

Casos derivados de los criterios de aceptación de [[RF30]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF30-01 | **Dado** un comentario, **cuando** alguien responde, **entonces** la respuesta aparece anidada bajo él. | pasa |
| CP-RF30-02 | **Dado** el docente, **cuando** oculta un mensaje, **entonces** deja de verse para los estudiantes y queda registrado. | automatizado |
| CP-RF30-03 | **Dado** un hilo cerrado, **entonces** no admite respuestas nuevas. | pasa |
| CP-RF30-04 | **Dado** un mensaje nuevo, **entonces** se notifica según las preferencias ([[RF23]]). | automatizado |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF30-01 …')`.
