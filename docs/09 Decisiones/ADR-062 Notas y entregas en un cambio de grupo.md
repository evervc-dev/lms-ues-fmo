---
tipo: adr
id: ADR-062
estado: pendiente
fecha: 2026-10-31
afecta: ["[[RF09]]", "[[RF15]]", "[[RF17]]", "[[RF34]]", "[[Modelo entidad-relación]]", "[[Flujo Cálculo de nota final]]"]
fuentes: ["Decisión del usuario (31/10/2026)", "Revisión del código del 31/10/2026 (CP-RF09-08)"]
---

# ADR-062 · ¿Qué pasa con las notas y las entregas cuando se aprueba un cambio de grupo?

> [!warning] Pendiente de aceptar

## Contexto
CP-RF09-08 de [[RF09]] pide que, al aprobar el cambio de grupo, «el estudiante pase a la nueva sección **conservando entregas y notas**». El código no lo cumplía y lo decía: `MatriculaService::aprobarCambio()` llevaba escrito *«Pendiente (RF09): las entregas y notas quedan en la matrícula anterior; falta definir cómo se trasladan a la nueva»*.

`aprobarCambio()` deja la matrícula de origen en `transferred`, crea la nueva con `enrolled_via = 'group_change'` y las enlaza con `enrollments.transferred_to_id`. Todo lo académico cuelga de `enrollment_id`, no del estudiante.

Dos datos del esquema deciden la cuestión:

1. **No se pueden mover las filas.** `grades`, `submissions` y `attendance_records` tienen una **clave foránea compuesta** `(enrollment_id, section_id)` → `enrollments (id, section_id)`, y la migración lo dice en un comentario: «La nota, su actividad y la matrícula son de la misma sección». Mover una de esas filas obliga a cambiarle también `section_id`, es decir, a afirmar que la nota o la asistencia ocurrió donde no ocurrió.
2. **No se puede encadenar por actividad.** `grading_components` y `grade_items` llevan `section_id`, y `grades` tiene además `unique(['grade_item_id', 'enrollment_id'])` y una segunda foránea compuesta contra `grade_items`. Una actividad solo puede tener notas de matrículas **de su propia sección**: el grupo 02 tiene sus propias actividades, creadas por su docente. Buscar «la misma `grade_item` en dos matrículas de la cadena» es imposible por construcción.

Lo que **sí** es estable entre los dos grupos es el **cómputo**: `ComputoService::copiarPlantilla()` copia `number`, `name`, las fechas y el peso desde `grading_component_templates` del período, así que el cómputo 1 del grupo 01 y el cómputo 1 del grupo 02 son el mismo cómputo.

## Decisión
*Decidida por el usuario el 31/10/2026: leer la cadena, sin mover filas, encadenando **por cómputo**.*

1. **No se mueve ni se copia ninguna fila.** El cambio de grupo no reescribe `grades`, `submissions`, `attendance_records` ni el progreso. La matrícula de origen conserva su historia completa.
2. **La cadena `transferred_to_id` es la fuente.** Se camina hacia atrás desde la matrícula activa (`CalificacionService::cadena()`), con un tope de 20 saltos por si los datos vinieran mal.
3. **Las notas se heredan por cómputo, no por actividad** (`CalificacionService::notasDeCadena()`):
   - Para cada cómputo del grupo nuevo, si el estudiante **no tiene ninguna nota** en ese cómputo y en un grupo anterior de la cadena sí tiene notas en el cómputo del **mismo `number`**, se usa la **nota de cómputo** ya obtenida allá, calculada solo con sus notas **publicadas**.
   - Para un mismo `number` gana la matrícula **más reciente** que tenga notas.
   - En cuanto el docente del grupo nuevo califica ese cómputo, **manda el grupo nuevo**: la herencia desaparece.
   - La nota heredada pesa con el `weight_percent` del **grupo nuevo**, que es donde se publica el acta.
4. **Entregas** ([[RF15]]): el docente del grupo nuevo ve las entregas anteriores como **solo lectura**, identificadas con su grupo de origen (`ActividadService::entregasDeCadena()`). No se califican desde allí.
5. **Asistencia** ([[RF19]], [[RF22]]): **no** se encadena. El porcentaje se calcula sobre las sesiones de cada sección, porque las de la anterior ya no le corresponden. El cambio de grupo no perjudica ni favorece la asistencia.
6. **`enrollment_results` se escribe en la matrícula activa**, la última de la cadena, y su nota final **incluye** la herencia. Su `policy_snapshot` gana la clave **`inherited_from`** —con `component_number`, `group`, `section_id`, `enrollment_id` y `score`— **solo** cuando hubo herencia, para que el resultado congelado diga de dónde salió cada cómputo. Sin traslado el snapshot no cambia de formato.
7. **El acta** la publica el docente de la sección nueva, con la nota calculada sobre la cadena.
8. **El riesgo académico** ([[RF17]]) también cuenta el cómputo heredado, con su peso completo. Sin eso, quien cambia de grupo se marcaría «en riesgo» por una nota que sí tiene, solo que en el grupo anterior.

## Consecuencias
- Nada que ya esté auditado se reescribe: se respeta la inmutabilidad de [[ADR-027 Base de datos híbrida]] y no hay que relajar ninguna clave foránea.
- El costo se traslada a la **lectura**: `notaComputo()` y `notaFinal()` reciben la cadena, y el libro y el congelado la resuelven en bloque (`cadenasDeSeccion()`) con una sola consulta que, sin traslados, devuelve cero filas.
- **La granularidad se pierde en el salto:** del grupo anterior se conserva la nota del cómputo, no el detalle actividad por actividad. En el libro del grupo nuevo esas celdas quedan vacías y lo que aparece es la nota del cómputo, marcada con su grupo de origen. Es inevitable: las actividades de los dos grupos no son las mismas.
- **Pesos distintos entre grupos:** si el docente del grupo anterior cambió los pesos de sus actividades, la nota del cómputo heredado se calculó con los suyos, pero pesa con el `weight_percent` del grupo nuevo. Es la decisión del punto 3 y conviene saberlo al revisar un caso.
- **El avance por unidad queda fuera de esta ADR.** `resource_progress` cuelga de recursos que pertenecen a las unidades de cada sección, así que no hay nada equivalente que sumar, y `ProgresoService` todavía no existe (Plan 5 de [[Planes de implementación pendientes]]). Cuando se cree, [[RF18]] tendrá que decidir qué hace con la cadena.
- La asistencia partida en dos secciones puede extrañar a quien revise un caso concreto; queda explicado aquí y en [[RF09]].
