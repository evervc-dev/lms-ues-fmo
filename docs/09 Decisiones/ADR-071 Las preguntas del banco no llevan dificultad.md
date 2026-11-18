---
tipo: adr
id: ADR-071
estado: pendiente
fecha: 2026-11-17
afecta: ["[[RF16]]", "[[ADR-009 Tipos de pregunta]]", "[[ADR-031 Modelo de datos v2 en inglés]]", "[[Modelo entidad-relación]]", "[[Docente]]", "[[Actividades y evaluaciones]]"]
fuentes: ["Informe de QA del 17/11/2026", "Decisión del usuario del 17/11/2026"]
---

# ADR-071 · Las preguntas del banco no llevan dificultad

> [!info] Pendiente de aceptar por el equipo. Quita una característica que [[RF16]] describía y que nadie usaba.

## Contexto
El banco guardaba una **dificultad** (fácil, media, difícil) por pregunta, y el cuestionario podía sortear preguntas filtrando por ella. En el informe de QA del 17/11 el equipo pidió quitarla del formulario: el docente debía elegirla en cada pregunta sin que sirviera para nada, porque el cuestionario filtra por **tema**, no por dificultad, y las «estadísticas de preguntas difíciles» de [[RF16]] salen de los resultados del examen, no de esta etiqueta.

## Opciones
- **A. Quitarla del todo:** fuera de la pantalla, del sorteo y de la base (`questions.difficulty` y `quiz_question_rules.difficulty`).
- **B. Quitarla solo de la pantalla** y dejar las columnas sin usar: el modelo documenta algo que ya no existe.
- **C. Dejarla y darle uso** (filtrar el sorteo por dificultad): nadie lo pidió y alarga el formulario de cada pregunta.

## Decisión
**A.** *Decidida por el usuario el 17/11/2026.* La pantalla dejó de pedirla el 17/11 y la migración `2026_11_17_230458_drop_question_difficulty` borra las dos columnas. El índice de `questions` pasa a `(bank_id, topic)`.

## Consecuencias
- Se pierde la dificultad ya guardada en las preguntas sembradas. No se usaba en ningún cálculo ni reporte.
- `quiz_question_rules` sortea por banco y tema. El sorteo por dificultad desaparece también de la copia de evaluaciones ([[RF14]]).
- **Se corrige de paso un defecto:** el CHECK `quiz_question_rules_quantity` exigía `quantity >= 1`, así que la regla «cantidad al azar = 0 (todas)» que se habilitó el 17/11 habría sido **rechazada por PostgreSQL**; en SQLite no existe ese CHECK ([[ADR-033 Migraciones adaptativas]]) y las pruebas no lo veían. Ahora admite `quantity >= 0`.
- [[RF16]], [[Docente]] y [[Actividades y evaluaciones]] dejan de mencionar la dificultad en el banco. Lo que [[ADR-031 Modelo de datos v2 en inglés]] decía de `quiz_question_rules` queda enmendado aquí.
