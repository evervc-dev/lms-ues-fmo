---
tipo: adr
id: ADR-037
estado: aceptada
fecha: 2026-10-17
afecta: ["[[RF04]]", "[[RF08]]", "[[RF09]]", "[[Matriz RBAC]]", "[[ADR-030 Matrícula ampliada]]", "[[ADR-032 Ajustes por el documento de roles]]", "[[ADR-036 Auditoría de asignaciones académicas]]"]
fuentes: ["Definición de la jerarquía de usuarios del equipo (17/10/2026)"]
---

# ADR-037 · Jerarquía de creación y asignación de roles

## Contexto
Hasta ahora solo el Administrador creaba usuarios y asignaba roles. El Coordinador importaba estudiantes de su carrera ([[ADR-030 Matrícula ampliada]]). El equipo definió quién crea a quién:
- **Administrador:** a todos. Su foco es el control institucional: jefes, coordinadores, soporte y otros administradores.
- **Soporte:** estudiantes y, bajo autorización, docentes (solo la cuenta base). Nunca administradores, jefes ni coordinadores. Atiende matrículas extemporáneas y corrige datos.
- **Jefe de Departamento:** asigna roles al personal académico de su departamento (coordinadores y docentes). Asigna docentes a materias, matricula estudiantes y cambia el cupo de las secciones.
- **Coordinador:** sin creación de usuarios; asigna docentes e inscribe estudiantes en su ámbito.

## Decisión
*Definida y aceptada por el equipo el 17/10/2026.*

1. **Roles que asigna cada rol** (`JerarquiaService::ROLES_QUE_ASIGNA`):
   - **Administrador:** todos los roles. Es el **único** que desactiva y reactiva cuentas.
   - **Soporte:** Estudiante. Docente solo si el Administrador le concede el permiso directo `usuarios.crear-docente`; es por usuario y no va en el rol.
   - **Jefe:** Docente y Coordinador.
   - **Coordinador:** ninguno.
2. **Soporte** crea cuentas y corrige nombres solo de usuarios **con roles** y cuyos roles asigna todos. No puede editar a un Administrador, un Jefe o un Coordinador. Sí da el primer rol a una cuenta sin roles, como la de un autorregistro ([[ADR-038 Autorregistro sin rol]], 17/10/2026). Puede importar estudiantes de cualquier carrera.
   - Con `usuarios.crear-docente` **da** el rol Docente, pero **no lo quita** ni cambia el departamento de un perfil docente que ya existe («solo la cuenta base»).
   - El permiso cuenta solo si es **directo** sobre la persona: si alguien lo agrega al rol Soporte, no tiene efecto.
   - Si un rol recibe `usuarios.gestionar` fuera de la matriz (pantalla de roles, RF03), queda con alcance P y no gestiona usuarios.
3. **El Jefe no crea cuentas.** Sobre usuarios existentes, da o quita:
   - **Docente:** con el perfil en su departamento.
   - **Coordinador:** de las carreras de su departamento.

   No toca una asignación vigente de otro departamento, no edita nombres y no da otros roles.
   - **Lista de usuarios** (`usuarios.ver` D): ve el personal de su departamento (docentes con perfil en él y coordinadores de sus carreras) y, para incorporar a alguien, la cuenta cuyo correo escribe completo. Esa cuenta queda en su sesión como «incorporable».
   - **Ficha:** solo la de su personal o de una cuenta incorporable (403 en otro caso), sin los accesos ni el dispositivo.
   - Un perfil docente de otro departamento lo bloquea aunque la persona hoy no tenga el rol.
4. **El Coordinador conserva la importación** de estudiantes de su carrera ([[ADR-030 Matrícula ampliada]]), aunque no crea usuarios uno por uno ni asigna roles. Así lo decidió el equipo.
5. **Carga académica:**
   - **Jefe:** asigna docentes a secciones de las materias de su departamento (`secciones.asignar-docente` D) y cambia su cupo (`secciones.cupo` D, permiso nuevo). No crea secciones ni horarios.
   - **Cupo:** entre 1 y 200; no puede quedar por debajo de los inscritos activos ni del cupo mínimo de la sección. Se valida con la sección bloqueada, dentro de la transacción.
6. **Soporte matricula** en cualquier sección (`matricula.gestionar` G), con el mismo control de choque de horario y de cupo. Lo «extemporáneo» es de procedimiento: el período no tiene una ventana de matrícula. *(Superado por [[ADR-051 Fecha límite de inscripción por departamento]]: hay fecha límite por departamento y, vencida, tampoco Soporte inscribe sin prórroga.)* La matrícula queda con `enrolled_via = 'admin'` y el `enrolled_by` de Soporte.

## Consecuencias
- Conceder o retirar `usuarios.crear-docente` queda en `audit_logs`: trigger `model_has_permissions_audit` (`database/sql/pgsql/07_audit_permissions.sql`, migración `2026_10_17_000000_audit_direct_permissions`).
- Reemplaza el punto 2 de [[ADR-032 Ajustes por el documento de roles]] («Soporte no matricula») y la parte del punto 6 que decía «el Jefe no asigna docentes».
- Permisos nuevos en la [[Matriz RBAC]]: `usuarios.crear-docente` (directo, sin rol), `usuarios.roles.asignar` (Jefe D) y `secciones.cupo` (Adm G, Jefe D).
- Cambian los alcances de:
  - `usuarios.ver`: + Jefe D;
  - `usuarios.gestionar`: + Soporte G*;
  - `usuarios.importar`: + Soporte G*;
  - `secciones.asignar-docente`: + Jefe D;
  - `matricula.gestionar`: + Soporte G.
- No hay migraciones. Las asignaciones siguen auditadas por [[ADR-036 Auditoría de asignaciones académicas]].
- «Auxiliares» no es un rol del sistema; no se crea.

## Pendiente
- Si se quiere distinguir la matrícula de Soporte en `enrolled_via` (p. ej. `support`), hace falta cambiar el CHECK `enrollments_via`.
- La importación de docentes no crea su perfil docente; esto ya pasaba antes. Se registra como defecto aparte.
