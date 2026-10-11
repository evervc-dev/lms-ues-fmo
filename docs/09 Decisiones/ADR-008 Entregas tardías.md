---
tipo: adr
id: ADR-008
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF15]]"]
fuentes: [Alcance - Aula virtual.pdf, Requerimientos.pdf p.7]
---

# ADR-008 · ¿Cómo se trata una entrega tardía?

## Contexto
El **alcance** describe una fecha de gracia: la tarea se acepta y se marca como «entregada tarde». **[[RF15]]** pide entregas tardías «con una penalización automática».

## Opciones
- **A.** Solo marcar «tarde», sin penalización.
- **B.** Penalización automática fija.
- **C.** Configurable por tarea: cierre estricto, gracia sin penalización, o gracia con penalización de X % por día u hora.

## Recomendación
**C.** Cubre las dos fuentes y deja que cada docente decida.

## Decisión
**C. Configurable por tarea:** cierre estricto, gracia sin penalización, o gracia con penalización de X %. Ver [[RF15]]. *Decidido por el equipo el 10/10/2026.*
