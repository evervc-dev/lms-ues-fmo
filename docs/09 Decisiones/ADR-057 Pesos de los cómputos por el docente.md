---
tipo: adr
id: ADR-057
estado: pendiente
fecha: 2026-10-28
afecta: ["[[RF17]]", "[[RF34]]", "[[Flujo Cálculo de nota final]]", "[[Matriz RBAC]]"]
fuentes: ["Decisión del usuario (28/10/2026)", "docs/12 Desarrollo/Planes de implementación pendientes.md (Plan 2)"]
---

# ADR-057 · Pesos de los cómputos por el docente

> [!warning] Pendiente de aceptar

## Contexto
[[RF17]] pide rechazar ponderaciones que no suman 100 % (CP-RF17-01), y el [[Flujo Cálculo de nota final]] exige el 100 % «en cada nivel». El ER dice que `grading_components` se copia de `grading_component_templates` y es ajustable, pero ningún código lo copiaba: una sección nueva quedaba sin cómputos y el docente no podía crear actividades. La [[Matriz RBAC]] no tiene un permiso para configurar pesos, y las actividades se crean de una en una, así que la suma del 100 % no se puede exigir al crear cada una.

## Decisión
*Aprobada por el usuario el 28/10/2026.*

1. **Se copia al crear la sección.** `CargaAcademicaService::crearSeccion()` copia la plantilla del período en la misma transacción (`ComputoService::copiarPlantilla()`, idempotente). Una migración de datos copia la plantilla a las secciones existentes sin cómputos de períodos no cerrados.
2. **El docente ajusta los pesos de sus secciones** con `calificaciones.gestionar` (alcance S), en **Notas → Pesos**. No se crea un permiso nuevo.
3. **Se bloquea** con alguna nota o actividad publicada, o con el acta `published` o `validated`. Con el acta devuelta se puede volver a ajustar.
4. **Dos momentos de validación:**
   - Al guardar: los cómputos deben sumar **exactamente 100 %**; las actividades de un cómputo, **100 % o menos**; ningún peso puede ser 0 o negativo.
   - Al **publicar el acta** ([[RF34]]): cómputos y actividades de cada cómputo deben sumar **exactamente 100 %**. El mensaje nombra el cómputo que no cuadra. Mientras no cuadre, el libro de notas lo avisa.
5. La plantilla del período (30/30/40) no se edita desde la aplicación.

## Alternativas consideradas
- **Coordinador con un permiso nuevo:** más control de la carrera, pero exige cambiar la Matriz RBAC y añade un paso para cada sección.
- **Solo copiar la plantilla, sin edición:** más simple, pero no permite adaptar la evaluación de cada asignatura.
- **Exigir el 100 % al publicar notas:** detecta antes el error, pero impide publicar la primera actividad de un cómputo con varias.

## Consecuencias
- `grading_components` y `grade_items` no están en la lista de `fn_audit_row`: el cambio de pesos no deja rastro en la auditoría. Hay que decidir si se agregan (cambio de esquema; se propone por separado).
- El permiso `calificaciones.corregir` del Docente (S, período abierto) sigue sin uso: el docente cambia la nota con `calificar()`.
