---
tipo: adr
id: ADR-063
estado: pendiente
fecha: 2026-10-31
afecta: ["[[RF09]]", "[[RF17]]", "[[RF19]]", "[[Modelo entidad-relación]]", "[[Flujo Matrícula y cambio de grupo]]"]
fuentes: ["Informe de QA del rol Jefe de Departamento (31/10/2026)"]
---

# ADR-063 · ¿Se puede reactivar una matrícula retirada por error?

> [!warning] Pendiente de decidir. **No implementada:** hoy el retiro es irreversible.

## Contexto
El informe de QA del rol **Jefe de Departamento** lo plantea así:

> «¿Por qué el jefe de departamento puede retirar un estudiante de un curso? … ¿qué pasaría si el jefe se equivoca o algo? Si se retiró un estudiante por error tendría que haber otro disparador o botón que se pueda volver a activar ese estudiante con la información que tenía antes de eliminarlo.»

Dos cosas distintas:

1. **Que el Jefe pueda retirar es una decisión tomada, no un defecto.** La [[Matriz RBAC]] le da `matricula.gestionar` con alcance **D** ([[ADR-030 Matrícula ampliada]], [[ADR-032 Ajustes por el documento de roles]], [[ADR-037 Jerarquía de creación y asignación de roles]]). El retiro exige motivo, queda en `enrollment_changes` y se audita.
2. **Que no se pueda deshacer sí es un hueco real.** `MatriculaService::retirar()` pone `enrollments.status = 'withdrawn'` y **no existe ningún camino de vuelta**: ni Service, ni pantalla, ni permiso.

El riesgo es concreto: un retiro equivocado deja al estudiante fuera de su sección en plena marcha, y hoy la única salida es volver a matricularlo, lo que crea una matrícula **nueva**. Esa matrícula nueva no lleva sus notas ni su asistencia, porque todo cuelga de `enrollment_id` ([[ADR-062 Notas y entregas en un cambio de grupo]]), así que el estudiante pierde lo cursado.

## Opciones

### A · Revivir la misma matrícula (`withdrawn` → `active`)
Un método `reactivar()` devuelve la fila a `active`, con motivo obligatorio y su propio `change_type` en `enrollment_changes`.
- **A favor:** conserva notas, entregas y asistencia sin tocar una sola fila: son de esa `enrollment_id`.
- **En contra:** hay que **validar otra vez el cupo y el choque de horario**, porque entre el retiro y la reactivación la sección pudo llenarse o el estudiante pudo matricularse en otra que choca. Si el cupo ya está lleno, la reactivación se rechaza y el Jefe se queda sin salida.
- Hay que decidir si se permite con el período **cerrado** (probablemente no: el resultado ya está congelado en `enrollment_results`).

### B · Ventana de gracia
Igual que A, pero solo dentro de un plazo corto desde el retiro (por ejemplo 48 horas) y reservando el cupo durante ese plazo.
- **A favor:** cubre el caso real —el error que se nota enseguida— sin dejar abierta una puerta indefinida.
- **En contra:** reservar el cupo complica el conteo de `max_capacity`, que hoy es un simple `count` de matrículas activas.

### C · No reactivar: que lo arregle Soporte volviendo a matricular
Se deja como está y se documenta que un retiro equivocado se corrige con una matrícula nueva.
- **A favor:** cero código.
- **En contra:** el estudiante **pierde sus notas y su asistencia**, que quedan en la matrícula retirada. Es exactamente lo que el Jefe teme.

### D · Confirmación más fuerte en vez de deshacer
No se puede deshacer, pero el retiro pide una confirmación que nombre al estudiante y su sección, y avisa de que es irreversible.
- **A favor:** barato y reduce el error humano.
- **En contra:** no resuelve el error ya cometido.

## Recomendación
**A, con la restricción de período abierto**, y **D como complemento**: revivir la misma matrícula es lo único que conserva la historia académica, y es coherente con [[ADR-062 Notas y entregas en un cambio de grupo]], que ya decidió que la historia vive en su `enrollment_id` y no se mueve. La validación de cupo y choque al reactivar es la misma que ya hace `validarCupoYChoque()`.

## Decisión
_Pendiente del equipo._

## Consecuencias si se acepta A
- `MatriculaService::reactivar()`, con permiso `matricula.gestionar` y el alcance de siempre, dentro de `DbContext::run()` con un evento de auditoría propio (`enrollment.reactivated`).
- Un `change_type` nuevo en `enrollment_changes`: hay un CHECK que lo valida, así que la migración debe ampliarlo.
- Pantalla: la fila retirada gana la acción «Reactivar», con motivo obligatorio.
- Caso de prueba nuevo en [[CP-RF09]], con el cupo lleno y el choque de horario como casos de rechazo.
