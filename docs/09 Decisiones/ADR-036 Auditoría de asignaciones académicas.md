---
tipo: adr
id: ADR-036
estado: pendiente
fecha: 2026-10-13
afecta: ["[[Modelo entidad-relación]]", "[[ADR-027 Base de datos híbrida]]", "[[ADR-029 Auditoría departamental del Jefe]]", "[[RF04]]", "[[RF05]]"]
fuentes: ["Diagnóstico de la revisión de interfaz del 13/10/2026"]
---

# ADR-036 · Auditoría de asignaciones académicas y roles atados

## Contexto
[[RF04]] solo permitía crear un usuario con un rol. Las asignaciones que definen el **alcance** de los datos (`App\Support\Alcance`) se escribían únicamente desde los seeders:
- perfil docente: `teacher_profiles`;
- carrera del estudiante: `student_profiles` y `student_careers`;
- coordinación: `career_coordinators`;
- jefatura: `department_heads`.

Por eso, un coordinador creado desde la interfaz no veía ninguna sección y un docente nuevo no aparecía en «Asignar docente» ([[RF08]]). Además, esas tablas no estaban en `fn_audit_row`. Un cambio de coordinador o de jefe cambia qué datos ve cada persona y no quedaba rastro.

## Decisión
*Propuesta por el equipo el 13/10/2026; pendiente de aceptación.*

1. **Roles atados a su asignación.** Cada rol exige su asignación al concederse:
   - **Coordinador:** una carrera.
   - **Jefe de Departamento:** un departamento.
   - **Docente:** un departamento (perfil docente).
   - **Estudiante:** carrera, plan de esa carrera, ciclo y carné.
   - **Administrador y Soporte:** no requieren asignación.
2. **Relevo con histórico.** Si la carrera ya tiene un coordinador vigente, a ese se le pone `valid_to = hoy` y el nuevo empieza con `valid_from = hoy`. Con el Jefe de Departamento se hace lo mismo. En la misma transacción, **al relevado se le quita el rol** (Coordinador o Jefe) si ya no le queda ninguna asignación vigente de ese tipo; conserva sus otros roles. Si ese era su único rol, queda sin roles: puede entrar, pero solo ve un inicio vacío hasta que el Administrador le asigne otro. No se releva al propio actor ni a la cuenta de emergencia: el relevo les quitaría un rol, y sus roles no los edita quien actúa. En la interfaz, cada coordinador tiene una sola carrera y cada jefe un solo departamento.
3. **Al quitar un rol:**
   - Coordinador y Jefe: se cierra su asignación vigente.
   - Docente y Estudiante: se conservan su perfil y su carrera, que forman parte del historial académico. El docente sin rol, o con la cuenta inactiva, deja de aparecer en «Asignar docente» y el servicio rechaza asignarlo.
   - **Bloqueos:** no se quita el rol Docente a quien tiene secciones asignadas en un período no cerrado (primero se reasignan en Carga académica), ni el rol Estudiante a quien tiene matrículas activas en un período no cerrado (primero se retiran).
4. **Carné inmutable.** Se fija al crear el perfil de estudiante y no se puede modificar. Si un estudiante cambia de carrera, la anterior queda `changed` y se crea la nueva activa. El ciclo admite de 1 a 12, igual que la importación.
5. **Reglas de roles:**
   - Todo usuario conserva al menos un rol.
   - **Nadie modifica sus propios roles**: lo hace otro Administrador. Así nadie puede concederse el rol Administrador aunque reciba `usuarios.gestionar`.
   - Nadie puede quitarle el rol de Administrador al último Administrador activo ([[RF03]]).
   - Los roles de la cuenta de emergencia no se editan ([[ADR-007 Inicio de sesión con Google]]).
6. **Permiso.** Todo esto se hace con `usuarios.gestionar` con **alcance G** (Administrador); si otro rol recibe el permiso por [[RF03]], su alcance no es G y el servicio lo rechaza. `roles.gestionar` sigue siendo solo para la matriz rol ↔ permiso. No se crean permisos y el Jefe no cambia ([[Matriz RBAC]]).
7. **Auditoría.** `fn_audit_row` se agrega a `teacher_profiles`, `student_profiles`, `student_careers`, `career_coordinators` y `department_heads`. Se aplica en `database/sql/pgsql/06_audit_assignments.sql`, con su reversa en `99_drop.sql`, mediante la migración `2026_10_13_000000_audit_identity_assignments`.

## Consecuencias
- Un usuario creado desde la interfaz queda listo para usarse, con su alcance correcto.
- En la auditoría, `teacher_profiles` y `department_heads` quedan con su `department_id`. `career_coordinators`, `student_careers` y `student_profiles` no tienen departamento derivable, así que solo las ve el Administrador, igual que `users` ([[ADR-029 Auditoría departamental del Jefe]]).
- Al desactivar una cuenta se cierran sus sesiones abiertas. Esto funciona con `SESSION_DRIVER=database` (el de la configuración actual); con otro driver haría falta un middleware que revise `is_active`.
- **Pendiente, como defecto aparte:** la importación masiva permite los roles Docente, Coordinador y Jefe sin crear su asignación (`teacher_profiles`, `career_coordinators`, `department_heads`), porque el CSV no trae departamento ni carrera para esos roles.
- **Límites conocidos de la auditoría:**
  - `fn_audit_row` toma `entity_id` de la columna `id`. En `teacher_profiles`, `student_profiles` y `model_has_roles` queda vacío; el usuario está en `old_values` y `new_values`.
  - El departamento de la auditoría sale del registro nuevo. Si un docente cambia de departamento, el Jefe del departamento de origen no ve el cambio (ADR-029).
- Las fechas de vigencia se guardan con la fecha UTC (`now()->toDateString()`), igual que `teaching_assignments`. Entre las 18:00 y las 24:00 de El Salvador queda la fecha del día siguiente. `Alcance` solo mira `valid_to IS NULL`, así que no hay efecto funcional.
