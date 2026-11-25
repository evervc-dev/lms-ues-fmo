---
tipo: manual
rol: "[[Jefe de Departamento]]"
ayuda_ruta: /ayuda/jefe-de-departamento
estado: revisado
capturas: pendientes
---

# Manual del Jefe de Departamento

Como Jefe de Departamento, usted organiza el personal, las asignaturas y los grupos teóricos de **su departamento**: da los roles de docente y coordinador, crea los grupos y les asigna docente, maneja la inscripción y los cambios de grupo, supervisa el trabajo de los docentes, revisa su desempeño y autoriza el cierre del período. Todo lo que ve y hace se limita a su departamento.

Sus opciones están en el menú, en el grupo **Jefatura**: **Carreras**, **Catálogo**, **Grupos teóricos**, **Carga académica**, **Usuarios**, **Matrícula**, **Cierre del período**, **Calendario de evaluaciones** y **Desempeño docente**. En **Consultas** tiene **Reportes**, **Estadísticas de oferta** y **Auditoría**.

## 1. Ingresar al sistema
*Requisitos: [[RF01]]*

1. Abra la plataforma y pulse **Iniciar sesión con Google**.
2. Elija su cuenta institucional **@ues.edu.sv**.
3. Si no ve el grupo **Jefatura**, pida el rol al Administrador.
4. La sesión se cierra sola tras **30 minutos sin actividad**.

> [!example] Captura pendiente
> Pantalla: ingresar al sistema.

## 2. Dar los roles de docente y coordinador
*Requisitos: [[RF04]] · [[ADR-037 Jerarquía de creación y asignación de roles]]*

Usted no crea cuentas: las crean el Administrador o Soporte Técnico, o se crean solas cuando la persona entra por primera vez con su correo @ues.edu.sv. Lo que usted hace es darles el rol.

1. Abra **Usuarios** y busque a la persona por nombre o correo.
2. Entre a su cuenta y pulse **Roles y asignaciones**.
3. Marque **Docente** o **Coordinador** y elija la carrera o el departamento: solo los de su departamento. Revise y guarde.
4. Para ver quién coordina cada carrera, abra **Carreras** y luego la carrera.

> [!example] Captura pendiente
> Pantalla: dar los roles de docente y coordinador.

## 3. Administrar las asignaturas de su departamento
*Requisitos: [[RF07]] · [[ADR-072 El Jefe gestiona las asignaturas de su departamento]] · [[ADR-092 Archivar períodos, departamentos, carreras y asignaturas]]*

1. Abra **Catálogo**. Verá solo las asignaturas de su departamento.
2. Puede crear una asignatura, editarla (también la descripción que ve el estudiante al inscribirse) y elegir sus **prerrequisitos**, que pueden ser de cualquier departamento. Los prerrequisitos quedan registrados, pero no impiden inscribirse.
3. Con **Archivar** retira una asignatura que ya no se ofrece: deja de aparecer en las listas, pero sus notas y actas se conservan. No se puede archivar si tiene grupos en un período que no está cerrado. En **Archivados** puede **Desarchivarla**.
4. Los departamentos, las carreras, los planes y las aulas los administra el Administrador.

> [!example] Captura pendiente
> Pantalla: administrar las asignaturas.

## 4. Crear grupos teóricos, asignar docentes y cambiar cupos
*Requisitos: [[RF08]] · [[ADR-073 El Jefe crea los grupos teóricos de su departamento]] · [[ADR-074 El grupo teórico pertenece a una carrera y solo lo crean el Administrador y el Jefe]]*

1. Abra **Carga académica**. Verá los grupos teóricos del período de su departamento.
2. Pulse **Nuevo grupo teórico**: elija la carrera, la materia de su plan, el cupo, los bloques de horario y el aula. La plataforma no deja guardar si el aula está ocupada a esa hora. El Coordinador puede cambiar el horario y el aula, pero no crea grupos.
3. Con **Asignar docente** elija el docente de cada grupo. La plataforma no deja guardar si el docente ya tiene clase a esa hora o pasa su tope de carga.
4. Con **Cupo del grupo teórico** cambie cuántos estudiantes caben.
5. En **Grupos teóricos** verá todos los grupos con su cupo usado y su docente.

> [!example] Captura pendiente
> Pantalla: crear grupos teóricos y asignar docentes.

## 5. Inscripción: claves, plazos y cambios de grupo
*Requisitos: [[RF29]] · [[RF09]] · [[ADR-078 El Jefe resuelve el cambio de grupo y no se validan prerrequisitos]] · [[ADR-087 La clave de matrícula se puede volver a consultar]]*

Todo está en **Matrícula**, en sus pestañas:
1. **Claves por asignatura**: la clave de cada asignatura de su departamento, la que usan los estudiantes para inscribirse solos. Cambie una o use **Aplicar a todas**. Con **Ver clave** vuelve a consultar la vigente.
2. **Plazos**: en «Fecha límite por departamento», fije hasta qué día pueden inscribirse los estudiantes. Con **Prorrogar** la extiende para todo el departamento, y a un estudiante en particular le da más tiempo con **Otorgar prórroga**.
3. **Solicitudes de cambio de grupo**: usted es quien las resuelve. Use **Aprobar** o **Rechazar** (con motivo); el estudiante recibe un aviso. Solo se piden en las primeras 3 semanas del período.
4. **Estudiantes**: inscriba o retire estudiantes. Se revisan el cupo y el choque de horario; los prerrequisitos no se exigen.

> [!example] Captura pendiente
> Pantalla: inscripción y cambios de grupo.

## 6. Supervisar el trabajo de los docentes
*Requisitos: [[RF39]] · [[ADR-093 Permisos de supervisión del Jefe y lectura de sesiones de asistencia]]*

Desde **Grupos teóricos**, abra cualquier grupo de su departamento. Verá sus pestañas en **solo lectura**: no puede cambiar nada, solo consultar.
- **Contenidos**, **Actividades**, **Progreso** y **Analítica**: qué materiales y actividades publicó el docente y cómo avanzan los estudiantes.
- **Notas** y **Revisiones**: el libro de calificaciones y las solicitudes de revisión de nota.
- **Asistencia**: cada sesión con su fecha, modalidad, estado, **hora de apertura y de cierre**, si se abrió **dentro o fuera del horario** y, si fue fuera, el **motivo** que dio el docente. Al abrir una sesión verá a cada estudiante con su estado, su hora de entrada y de salida, cómo marcó y, en las clases virtuales, su **porcentaje en Meet**.
- **Foro**: los hilos y las respuestas, sin poder escribir.

> [!example] Captura pendiente
> Pantalla: supervisar una sección.

## 7. Revisar el desempeño docente
*Requisitos: [[RF33]]*

En **Desempeño docente** verá, por cada docente de su departamento en el período elegido:
- **Retraso**: minutos promedio entre el inicio de la clase y la apertura de la asistencia, y el porcentaje de sesiones abiertas **a tiempo** (hasta 15 minutos después).
- **Recursos**: materiales que subió a sus secciones.
- **Revisión**: horas promedio desde la entrega hasta que publica la nota, y cuántas entregas siguen **sin publicar**.
- **Notas publicadas** de las actividades ya cerradas.

Puede ordenar la tabla y descargarla en Excel, PDF o CSV. Pulse **Cómo se calculan** para ver el detalle. Son datos informativos: no califican al docente.

> [!example] Captura pendiente
> Pantalla: revisar el desempeño docente.

## 8. Autorizar el cierre del departamento
*Requisitos: [[RF34]]*

1. Al final del período, abra **Cierre del período**. Verá cada sección con el estado de su acta: **Sin publicar**, **Por validar**, **Devuelta** o **Validada**.
2. Cuando todas estén **Validadas**, pulse **Autorizar cierre**. Si falta alguna, la plataforma le dice cuáles.
3. Cuando todos los departamentos autorizaron, el Administrador cierra el período.

> [!example] Captura pendiente
> Pantalla: autorizar el cierre del departamento.

## 9. Consultar la auditoría
*Requisitos: [[RF05]]*

1. En **Auditoría** verá lo que pasó en su departamento: **Qué pasó**, **Sobre** qué y **Qué cambió**, con la fecha y quién lo hizo.
2. Filtre por usuario, fechas, acción o entidad, y descargue el resultado.
3. Los registros no se pueden modificar ni borrar.

> [!example] Captura pendiente
> Pantalla: consultar la auditoría.

## 10. Reportes y oferta académica
*Requisitos: [[RF25]] · [[RF10]]*

1. En **Reportes** elija **Asistencia por sección**, **Notas por sección**, **Estudiantes en riesgo** o **Carga docente**, filtre por período y carrera, y descárguelo. Con **Programar «…»** lo recibe por correo.
2. En **Estadísticas de oferta** verá, por asignatura, los inscritos, aprobados y reprobados, y una propuesta de grupos para el próximo período.

> [!warning] Fórmula provisional
> La propuesta de grupos usa una fórmula provisional hasta que la Facultad apruebe la oficial. Tómela como referencia.

> [!example] Captura pendiente
> Pantalla: reportes y oferta académica.

## Problemas frecuentes
| Si le pasa esto… | Haga esto |
|---|---|
| Una persona no aparece en **Usuarios** | Todavía no tiene cuenta: que entre una vez con su correo @ues.edu.sv, o pida que la creen Soporte Técnico o el Administrador |
| No puedo autorizar el cierre | Alguna acta no está validada: la pantalla le dice cuál. Hable con el Coordinador o el docente |
| No puedo desactivar una cuenta | Solo el Administrador desactiva cuentas |
| No puedo archivar una asignatura | Tiene grupos en un período que no está cerrado: espere al cierre |
| En una sección no veo botones para cambiar nada | Es lo esperado: usted supervisa en solo lectura; los cambios los hace el docente |
