---
tipo: adr
id: ADR-051
estado: aceptada
fecha: 2026-10-26
aceptada: 2026-10-27
afecta: ["[[RF29]]", "[[RF09]]", "[[ADR-030 Matrícula ampliada]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]", "[[ADR-050 Inscripción por catálogo y claves compartidas]]", "[[Matriz RBAC]]"]
fuentes: ["Decisión del usuario (26/10/2026)"]
---

# ADR-051 · Fecha límite de inscripción por departamento

> [!success] Aceptada el 27/10/2026
> **Supera a [[ADR-037 Jerarquía de creación y asignación de roles]] §6**, que decía que el período no tiene ventana de matrícula. **Restringe [[ADR-030 Matrícula ampliada]]:** pasada la fecha, tampoco la matrícula manual inscribe sin prórroga.

## Contexto
[[RF29]] dice que la clave funciona «hasta la fecha límite de inscripción», pero [[ADR-037 Jerarquía de creación y asignación de roles]] dejó el período sin ventana de matrícula. No había dónde guardar esa fecha: solo existía `enrollment_keys.valid_until`, que es por asignatura. El usuario pidió una fecha **configurable** que **admita prórrogas**.

## Decisión
*Decidido por el usuario el 26/10/2026.*

1. **Fecha por período y departamento.** Manda el **departamento de la carrera del estudiante** (`careers.department_id`), no el que ofrece la asignatura. Así todas las materias de un estudiante se cierran el mismo día.
2. **Quién la fija** (`matricula.plazo.gestionar`, permiso nuevo):
   - el Administrador (G), en cualquier departamento;
   - el Jefe (D), en sus departamentos;
   - el Coordinador (C), en los departamentos de las carreras que coordina.
3. **Sin fecha fijada**, rige el inicio del período más `enrollment_deadline_weeks_default` semanas (2), igual que ya ocurre con `group_change_weeks`.
4. **Prórrogas:**
   - **De departamento** (`matricula.plazo.prorrogar`, Jefe D): nueva fecha y **motivo obligatorio**. La fecha original se conserva en `enrollment_deadlines.deadline`.
   - **Por estudiante** (`matricula.prorroga.otorgar`, Jefe D y Coordinador C): nueva fecha y **motivo obligatorio**, en `enrollment_extensions`.
5. **Plazo efectivo del estudiante:** la fecha más tardía entre la del departamento (o la de por defecto), la prórroga del departamento y la prórroga personal. Cuenta el día completo.
6. **Pasado el plazo no se inscribe a nadie,** ni con clave ni manualmente (docente, Coordinador, Jefe, Soporte ni Administrador), salvo con una prórroga. El control está en el camino común de la matrícula. El cambio de grupo conserva su propio plazo.

## Consecuencias
- **Tablas nuevas,** auditadas con triggers (`12_audit_enrollment_deadlines.sql`):
  - `enrollment_deadlines`, con UNIQUE por período y departamento, y la prórroga posterior a la fecha;
  - `enrollment_extensions`, con UNIQUE por período y estudiante.
- **Tres permisos nuevos** en la [[Matriz RBAC]].
- **Vista «Matrícula · Plazos»** (`/matricula/plazos`). La página de inscripción del estudiante muestra hasta cuándo puede inscribirse.
- **Seeders:** `DemoAcademicSeeder` fija la fecha del período activo de ejemplo en su fecha de fin, para que el entorno de desarrollo pueda inscribir. No puede hacerlo `InstitutionSeeder` porque `set_by` necesita un usuario y ese seeder corre antes.
- **Auditoría y RLS:** la auditoría de `enrollment_deadlines` la ve el Jefe de su departamento. La de `enrollment_extensions` queda con `department_id` NULL, porque `fn_audit_department` no la resuelve: por ahora solo la ve el Administrador. Queda pendiente de decidir.
- **Riesgo:**
  - Cuando el Coordinador fija la fecha del departamento, la fecha vale también para las otras carreras de ese departamento.
  - Una fecha mal puesta bloquea toda la matrícula del departamento. Lo mitigan la prórroga y que la vista muestre si el plazo está vencido.
- **CP-RF29-14 a 20.**
