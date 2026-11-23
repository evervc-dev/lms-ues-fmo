---
tipo: adr
id: ADR-089
estado: pendiente
fecha: 2026-11-22
afecta: ["[[RF07]]", "[[RF29]]", "[[RF37]]", "[[ADR-080 El grupo teórico es de la asignatura y lo comparten las carreras]]"]
fuentes: ["Pedido del usuario del 22/11/2026"]
---

# ADR-089 · Una asignatura va en un solo plan de cada carrera

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- Una asignatura podía estar en el Plan 98 y en el Plan 25 de ISI a la vez, y en los datos de prueba casi todas lo estaban: la del plan A salía también en el plan B (Inscripción, Mi plan, catálogo).
- El usuario pidió que las materias no se mezclen entre planes y que no se pueda agregar la misma materia a los dos.

## Opciones
- **A. Un solo plan por carrera.** Se puede compartir entre carreras distintas (ADR-080), pero no entre dos planes de la misma.
- **B. Un solo plan en total**, aunque sea de otra carrera.
- **C. Dejarlo libre.**

## Decisión
**A.** *Decidida por el usuario el 22/11/2026.*
- `CatalogoService::guardarAsignatura()` rechaza dos planes de la misma carrera: «Una asignatura solo puede estar en un plan de cada carrera». En el formulario, la carrera ya elegida en otra fila no se ofrece.
- Datos de prueba (`InstitutionSeeder`): cada asignatura de ISI está en un solo plan. Las de los ciclos 1 a 3 son del Plan 25 y las de 4 a 10, del Plan 98, donde hay estudiantes de cada uno. Cada plan recibe las equivalentes del otro con código propio (MAT115 → MAT118 en el Plan 98, BAD115 → BAD112 en el Plan 25), así los dos tienen su malla completa. Los prerrequisitos se enlazan dentro de cada plan.

## Consecuencias
- Una base que ya tenga una asignatura en dos planes de la misma carrera la conserva hasta que alguien la edite; al guardarla, debe quedar en uno solo.
- Para un plan nuevo con una materia equivalente se crea otra asignatura con su propio código.
