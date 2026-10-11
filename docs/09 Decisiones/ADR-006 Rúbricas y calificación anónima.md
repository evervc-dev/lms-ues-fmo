---
tipo: adr
id: ADR-006
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF15]]", "[[RF17]]", "[[Docente]]"]
fuentes: [Alcance - Aula virtual.pdf, Roles y permisos.pdf p.3, Requerimientos.pdf p.7-8]
---

# ADR-006 · ¿Entran las rúbricas y la calificación anónima?

> [!note] Actualizada por [[ADR-032 Ajustes por el documento de roles]]
> La **calificación anónima entra en el alcance**: bandera `assessments.is_anonymous`.

> [!note] Complementada por [[ADR-065 La rúbrica es parte de la tarea]] (pendiente)
> Dónde se arma la rúbrica: dentro de cada tarea, no en una lista aparte; los exámenes no la llevan.

## Contexto
El **alcance** las pone en «Posibles integraciones», es decir, opcionales. **Roles y permisos** las lista como funciones del Docente. **[[RF15]]** y **[[RF17]]** incluyen rúbricas, pero no la calificación anónima.

## Opciones
- **A.** Rúbricas dentro del alcance; calificación anónima como opcional (P12).
- **B.** Ambas dentro del alcance.
- **C.** Ambas opcionales.

## Recomendación
**A.** Las rúbricas están en dos RF y el 85.7 % de los docentes las pide. La calificación anónima no tiene RF y se puede agregar después sin rediseño: es un interruptor por actividad que oculta nombres hasta publicar.

## Decisión
**A. Las rúbricas entran al alcance** ([[RF15]], [[RF17]]). **La calificación anónima queda opcional** para una fase posterior. *Decidido por el equipo el 10/10/2026.*
