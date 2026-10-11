---
tipo: adr
id: ADR-053
estado: pendiente
fecha: 2026-10-27
afecta: ["[[RF03]]", "[[RF04]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]", "[[Matriz RBAC]]"]
fuentes: ["Informe de QA del rol Administrador (27/10/2026), hallazgo BUG-01", "Decisión del usuario (27/10/2026)"]
---

# ADR-053 · Roles incompatibles con el de Estudiante

> [!warning] Pendiente de aceptar
> **Amplía [[ADR-037 Jerarquía de creación y asignación de roles]]**, que define quién asigna cada rol pero no qué roles pueden coexistir en una cuenta.

## Contexto
El sistema permite varios roles por cuenta y [[ADR-037 Jerarquía de creación y asignación de roles]] regula **quién** los asigna, pero nunca dijo qué combinaciones son válidas. El **informe de QA** (27/10/2026) registró como **BUG-01**, de gravedad alta, que una misma cuenta tenía a la vez **Estudiante, Docente y Soporte Técnico**, y lo describió así: «Creería que un estudiante activo no debería poder ejercer como docente al mismo tiempo. Probablemente los demás roles sí podrían coexistir, más no con el de Estudiante».

El riesgo no es solo conceptual: el alcance `M` (sus matrículas) y el alcance `S` (sus secciones) se resuelven por rol, así que una cuenta con ambos roles ve la misma sección como estudiante y como docente, y podría calificarse a sí misma.

## Decisión
*Decidida por el usuario el 27/10/2026.*

1. **El rol Estudiante es incompatible con Docente y con Soporte Técnico.** Una cuenta no puede tener Estudiante junto con ninguno de los dos.
2. **Los demás roles siguen pudiendo coexistir** entre sí y con Estudiante (Coordinador y Jefe de Departamento son docentes con funciones añadidas, y el documento de roles lo contempla).
3. **Se valida en `JerarquiaService`**, en el camino común de asignación de roles, así que vale para el Administrador, para Soporte y para el Jefe en su departamento, y también para la carga masiva.
4. **El mensaje dice qué choca**: «Una cuenta con el rol Estudiante no puede tener además el rol Docente». No se quita el rol anterior en silencio.

## Consecuencias
- Las cuentas que **ya** tengan la combinación quedan como están: la regla se aplica al asignar, no retroactivamente. Hace falta un diagnóstico que las liste; queda para la tarea de limpieza.
- El seeder de demostración no debe crear cuentas con la combinación prohibida.
- Afecta a [[RF03]], [[RF04]] y sus casos de prueba.
