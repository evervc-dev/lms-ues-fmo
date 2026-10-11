---
tipo: adr
id: ADR-038
estado: aceptada
fecha: 2026-10-17
afecta: ["[[RF01]]", "[[RF04]]", "[[ADR-007 Inicio de sesión con Google]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]", "[[Flujo Inicio de sesión con Google]]"]
fuentes: ["Decisión del equipo (17/10/2026)"]
---

# ADR-038 · Autorregistro sin rol de cuentas @ues.edu.sv

## Contexto
[[ADR-007 Inicio de sesión con Google]] decía «No hay autorregistro»: una cuenta `@ues.edu.sv` que no existía en el sistema se rechazaba. Cada persona tenía que darse de alta antes, a mano o por CSV. El equipo considera que una cuenta institucional válida ya prueba que la persona pertenece a la universidad.

## Decisión
*Definida y aceptada por el equipo el 17/10/2026.*

1. **Una cuenta `@ues.edu.sv` válida que no existe se crea al primer ingreso, sin rol.** Las validaciones de ADR-007 se mantienen: `hd`, `email_verified` y el sufijo del correo, en el servidor.
2. **Qué se guarda:** el correo, los nombres (`given_name` y `family_name` de Google; si faltan, se parte el nombre completo), `is_active = true` y el `sub` vinculado. Sin roles ni asignaciones.
3. **Sin rol** entra a un inicio vacío con el aviso «Su cuenta está activa pero aún no tiene un rol asignado. Comuníquese con su coordinación o con Soporte técnico.». No ve módulos ni datos.
4. **Primer rol:**
   - **Soporte:** da Estudiante, y Docente con `usuarios.crear-docente`.
   - **Jefe:** da Docente o Coordinador en su departamento.
   - **Administrador:** cualquiera.

   Esto ajusta el punto 2 de [[ADR-037 Jerarquía de creación y asignación de roles]]: Soporte sí actúa sobre cuentas sin roles.
5. **Una cuenta desactivada sigue rechazándose.** Volver a entrar no la reactiva.
6. **Auditoría:** el alta va como escritura del sistema (`DbContext::system`), porque aún no hay actor. Queda en `audit_logs` con `event_type = user.self_registered`. En `login_history` es un `success` como cualquier otro.
7. **Seguimiento:** la lista de Usuarios tiene el filtro «Sin rol» y el resumen cuenta las cuentas sin rol. No se envían notificaciones.

## Consecuencias
- Cambia CP-RF01-03: de «se rechaza» a «se crea sin rol y entra».
- Cualquier cuenta `@ues.edu.sv` (egresados, personal de otras facultades) puede tener una cuenta sin rol. No ve datos, pero aparece en Usuarios; se depura con el filtro «Sin rol».
- Si dos primeros ingresos de la misma cuenta llegan a la vez, el UNIQUE de `institutional_email` decide y el segundo usa la cuenta creada.
- La carga por CSV y el alta manual siguen existiendo, para dar el rol y la asignación desde el principio.
