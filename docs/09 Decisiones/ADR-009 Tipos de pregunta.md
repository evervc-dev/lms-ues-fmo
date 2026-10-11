---
tipo: adr
id: ADR-009
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF16]]"]
fuentes: [Alcance - Aula virtual.pdf, Requerimientos.pdf p.8]
---

# ADR-009 · ¿Qué tipos de pregunta hay?

## Contexto
El **alcance** pide selección múltiple, verdadero/falso y texto corto. **[[RF16]]** agrega **ensayo**.

## Opciones
- **A.** Tres tipos, todos calificables automáticamente. La respuesta corta compara con las respuestas aceptadas.
- **B.** Cuatro tipos con ensayo, que se califica a mano.

## Recomendación
**B.** Requiere una bandeja de «pendientes de calificar» en [[RF24]], que ya existe para las tareas.

## Decisión
**B. Cuatro tipos:** opción múltiple, verdadero/falso, respuesta corta y ensayo. El ensayo se califica a mano desde la bandeja de pendientes ([[RF24]]). *Decidido por el equipo el 10/10/2026.*
