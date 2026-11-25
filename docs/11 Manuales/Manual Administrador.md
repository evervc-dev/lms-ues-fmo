---
tipo: manual
rol: "[[Administrador]]"
ayuda_ruta: /ayuda/administrador
estado: revisado
capturas: pendientes
---

# Manual del Administrador

Como Administrador, usted ve y gestiona toda la facultad: las cuentas y sus roles, los períodos académicos, el catálogo de asignaturas, las áreas de asistencia y la configuración técnica. También es el único que desactiva cuentas y el último paso para cerrar un período.

Sus opciones están en el menú, en el grupo **Administración**: **Usuarios**, **Importar estudiantes**, **Roles y permisos**, **Períodos**, **Catálogo**, **Carga académica**, **Matrícula**, **Áreas de asistencia**, **Clases canceladas**, **Integraciones** y **Monitoreo técnico**. En **Consultas** tiene **Reportes**, **Estadísticas de oferta** y **Auditoría**.

## 1. Ingresar al sistema
*Requisitos: [[RF01]]*

1. Abra la plataforma y pulse **Iniciar sesión con Google**.
2. Elija su cuenta institucional **@ues.edu.sv**.
3. La sesión se cierra sola tras **30 minutos sin actividad**.
4. Si Google no está disponible, existe una **cuenta de emergencia** con contraseña y doble factor. Su uso está reservado y documentado aparte, para el personal autorizado.

> [!example] Captura pendiente
> Pantalla: ingresar al sistema.

## 2. Crear cuentas y dar roles
*Requisitos: [[RF04]] · [[RF03]] · [[ADR-037 Jerarquía de creación y asignación de roles]]*

1. En **Usuarios**, pulse **Nuevo usuario**: datos de la cuenta, rol y asignación (carrera o departamento), y **Crear cuenta**. El correo debe ser @ues.edu.sv.
2. Para muchos estudiantes a la vez, use **Importar estudiantes** (sección 3).
3. Una persona que entra por primera vez con su correo @ues.edu.sv queda con su cuenta creada pero **sin rol**: búsquela en **Usuarios** con el filtro **Sin rol** y asígnele uno.
4. Para cambiar los roles de alguien, entre a su cuenta y pulse **Roles y asignaciones**. Usted puede dar cualquier rol.
5. A Soporte Técnico puede darle **Permitir crear docentes** desde la cuenta de esa persona. Sin ese permiso, Soporte solo crea estudiantes.
6. Para quitar el acceso a alguien, pulse **Desactivar** y escriba el motivo. Las cuentas no se borran, para conservar su historial; puede volver a activarla con **Reactivar**.

> [!example] Captura pendiente
> Pantalla: crear cuentas y dar roles.

## 3. Importar estudiantes desde un archivo
*Requisitos: [[RF04]]*

1. Abra **Importar estudiantes** y pulse **Descargar plantilla (CSV)**.
2. Llénela con carné, nombres, apellidos, correo, rol, carrera, plan y ciclo. Si la llena en Excel, guárdela como **CSV UTF-8**.
3. Suba el archivo y revise la **vista previa**: cuántas cuentas se crearán, cuántos duplicados se omiten y qué filas tienen error, con su motivo.
4. Confirme. En **Lotes anteriores** queda cada importación con sus filas creadas y con error.

> [!example] Captura pendiente
> Pantalla: importar estudiantes desde un archivo.

## 4. Revisar los permisos de cada rol
*Requisitos: [[RF03]]*

En **Roles y permisos** verá qué puede hacer cada rol. Busque un permiso por su nombre (por ejemplo, «notas.publicar») y pulse la casilla para darlo o quitarlo a un rol. Cada cambio queda en la auditoría.

> [!warning] Con cuidado
> Un permiso cambiado afecta a todas las personas con ese rol. Los permisos de cada rol están definidos por la Facultad: cámbielos solo si así se acordó.

## 5. Abrir, cerrar y reabrir un período
*Requisitos: [[RF06]] · [[RF34]]*

1. En **Períodos**, pulse **Nuevo período**: nombre (por ejemplo, «2027-I») y fechas. No puede cruzarse con las fechas de otro período.
2. Abra el período con **Gestionar** y avance sus etapas: planificación → definición de horarios → asignación docente → listo. Luego pulse **Poner en curso**. Solo puede haber un período en curso.
3. Para **cerrarlo**, todos los departamentos deben haber autorizado su cierre: lo ve en «Cierres departamentales». Pulse **Cerrar período**: las notas finales quedan congeladas.
4. Para **reabrirlo**, pulse **Reabrir período** y escriba el motivo. Se avisa a los Coordinadores. Las autorizaciones de cierre se conservan, así que puede volver a cerrarlo sin repetir las actas.
5. Desde el mismo detalle puede descargar el histórico del período en Excel, PDF o CSV.

> [!example] Captura pendiente
> Pantalla: abrir, cerrar y reabrir un período.

## 6. Calendario de evaluaciones y Google Calendar
*Requisitos: [[RF06]]*

1. Conecte primero su Google Calendar: **Mi perfil → Google Calendar → Conectar con Google**, con su cuenta @ues.edu.sv.
2. En el detalle del período, en **Calendario de evaluaciones**, pulse **Agregar fecha**: el evento (por ejemplo, «Primer parcial»), el inicio y, si quiere, el fin. Sin fin, es un evento de todo el día.
3. Pulse **Sincronizar con Google Calendar**. Cada fecha se crea en su calendario e invita a los docentes y estudiantes del período; Google les avisa por correo. La lista de invitados queda oculta.
4. Cada fecha muestra su estado: **Sin sincronizar**, **Enviando…**, **Sincronizada** o **Falló**, con el motivo. Si cambia una fecha, sincronice de nuevo: el evento se actualiza, no se duplica.
5. Al eliminar una fecha, su evento se borra y Google avisa a los invitados.

> [!example] Captura pendiente
> Pantalla: calendario de evaluaciones y Google Calendar.

## 7. Mantener el catálogo
*Requisitos: [[RF07]]*

1. En **Catálogo**, pulse **Nueva asignatura**: código único, nombre, créditos, departamento y **prerrequisitos** (marque todas las que correspondan).
2. Una asignatura que ya no se imparte se **Desactiva**; si nunca tuvo secciones, se puede **Eliminar**.
3. Con **Departamentos, carreras y aulas** administra la estructura de la facultad y agrega aulas.

> [!example] Captura pendiente
> Pantalla: mantener el catálogo.

## 8. Definir las áreas de asistencia
*Requisitos: [[RF26]]*

El estudiante solo puede marcar asistencia con QR si está dentro de un **área activa**. Las áreas valen para todas las clases presenciales.

1. En **Áreas de asistencia**, pulse **Nueva área** y elija la forma:
   - **Centro y radio**: haga clic en el mapa para poner el centro y fije el radio, entre 10 y 5 000 metros. Con **Usar mi ubicación** toma el lugar donde usted está.
   - **Polígono**: haga clic en el mapa para marcar cada vértice. **Deshacer** quita el último y **Limpiar** empieza de nuevo.
2. Deje margen: el GPS de un teléfono puede equivocarse varias decenas de metros.
3. Para dejar de aceptar un lugar, **Desactive** su área.

> [!example] Captura pendiente
> Pantalla: definir las áreas de asistencia.

## 9. Cancelar clases en toda la facultad
*Requisitos: [[RF32]]*

En **Clases canceladas**, pulse **Cancelar para la facultad**, elija la fecha y escriba el motivo (por ejemplo, un asueto). Ese día no cuenta para la asistencia, los estudiantes reciben un aviso y los eventos de las clases virtuales se borran de Google Calendar. Ahí mismo puede aprobar o rechazar las solicitudes de los docentes.

> [!example] Captura pendiente
> Pantalla: cancelar clases en toda la facultad.

## 10. Revisar la configuración técnica
*Requisitos: [[RNF09]]*

1. **Integraciones** muestra qué está configurado: el inicio de sesión con Google y el correo institucional. Los secretos nunca se muestran. Si algo aparece **Pendiente**, pida al equipo técnico que lo complete en el servidor.
2. **Monitoreo técnico** abre en otra pestaña el panel de las tareas en segundo plano (correos, avisos, importaciones, Google Calendar y reportes). Sirve para ver si alguna falló.
3. **Auditoría** registra todo lo que pasa en la plataforma. Usted ve la de toda la facultad. Los registros no se pueden modificar.

> [!example] Captura pendiente
> Pantalla: revisar la configuración técnica.

## Problemas frecuentes
| Si le pasa esto… | Haga esto |
|---|---|
| No me deja cerrar el período | Falta la autorización de algún departamento: revise «Cierres departamentales» |
| No me deja poner en curso un período | Ya hay otro en curso: ciérrelo primero |
| Alguien entró pero no ve nada | Su cuenta no tiene rol: búsquela con el filtro **Sin rol** |
| Una fecha del calendario dice «Falló» | Lea el motivo; si dice que ya no tiene Google Calendar conectado, vuelva a conectarlo y sincronice |
| Los estudiantes no pueden marcar «fuera del área» en un aula nueva | Agregue o amplíe un área que cubra ese edificio |
