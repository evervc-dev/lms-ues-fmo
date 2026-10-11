---
tipo: adr
id: ADR-064
estado: pendiente
fecha: 2026-10-31
afecta: ["[[RF06]]", "[[Ciclo de vida del período académico]]"]
fuentes: ["Informe de QA del rol Administrador, caso P-06 (26/10/2026)", "Reglamento de la Gestión Académico-Administrativa de la UES"]
---

# ADR-064 · ¿Se valida la duración del ciclo académico?

> [!warning] Pendiente de decidir. **No implementada:** hoy no hay ninguna validación de duración.

## Contexto
El caso **P-06** del informe de QA del rol Administrador probó períodos de 4 y de 40 semanas:

> «Duración del ciclo (4 o 40 semanas) · Resultado esperado: valida o advierte (el reglamento fija 20 semanas) · Resultado obtenido: **no existe restricción sobre la duración**.»

`PeriodoService` valida que la fecha de fin sea posterior a la de inicio y que no haya traslape con otro período ([[ADR-059 Reapertura de un período cerrado]] y la corrección del 27/10), pero **nada sobre cuánto dura**.

El problema de fondo: **el reglamento de la UES fija el ciclo en 20 semanas, y ningún RF del vault lo recoge**. No es que el sistema contradiga una decisión: es que la decisión no existe. Por eso el caso sigue en `Falla` y no se puede cerrar sin decidir.

## Opciones

### A · Advertir, sin bloquear
Si la duración se sale de un rango (por ejemplo 18 a 22 semanas), se muestra un aviso que se puede ignorar.
- **A favor:** el reglamento fija 20 semanas, pero la realidad incluye ciclos extendidos por paros, emergencias o acuerdos de la AGU. Bloquear impediría registrar lo que de verdad pasó.
- **En contra:** un aviso que se ignora no evita el error de tecleo.

### B · Rechazar fuera del rango
Validación dura en el Service, con el rango en `system_settings`.
- **A favor:** no entran períodos absurdos de 4 semanas.
- **En contra:** si un ciclo real se extiende, el Administrador no puede registrarlo sin que alguien cambie el parámetro.

### C · No validar nada
Se deja como está y se cierra P-06 como «no aplica».
- **A favor:** cero código.
- **En contra:** contradice el reglamento que la propia QA cita, y deja el hallazgo abierto.

## Recomendación
**A, con el rango en `system_settings`** (`period_weeks_min` y `period_weeks_max`, provisionales 18 y 22, como los demás parámetros de [[ADR-030 Matrícula ampliada]]). Es coherente con cómo el proyecto trata ya los parámetros académicos: valores provisionales que fija la Jefatura, no el equipo de desarrollo. Y avisar en vez de bloquear respeta que el calendario real de la UES se ha movido más de una vez.

## Decisión
_Pendiente del equipo. **Hace falta que alguien confirme el artículo del reglamento**: la QA lo cita de memoria y conviene la referencia exacta antes de fijar el rango._

## Consecuencias si se acepta A
- Dos parámetros nuevos en `system_settings`, con su seeder.
- El aviso en el formulario del período, no un error de validación.
- Caso P-06 pasa de `Falla` a cubierto, con su prueba en [[CP-RF06]].
