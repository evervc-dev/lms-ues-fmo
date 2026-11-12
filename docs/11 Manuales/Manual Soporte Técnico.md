---
tipo: manual
rol: "[[Soporte Técnico]]"
ayuda_ruta: /ayuda/soporte-tecnico
estado: revisado
capturas: pendientes
---

# Manual del Soporte Técnico

Como Soporte Técnico, usted ayuda a que los estudiantes puedan usar la plataforma: autoriza los cambios de teléfono para la asistencia, investiga por qué alguien no pudo marcar, y crea o corrige cuentas de estudiantes.

Sus opciones están en el menú, en el grupo **Soporte**: **Solicitudes de dispositivo**, **Diagnóstico de asistencia**, **Usuarios** e **Importar estudiantes**.

## 1. Ingresar al sistema
*Requisitos: [[RF01]]*

1. Abra la plataforma y pulse **Iniciar sesión con Google**.
2. Elija su cuenta institucional **@ues.edu.sv**.
3. La sesión se cierra sola tras **30 minutos sin actividad**.
4. En **Mi perfil → Notificaciones** verá los avisos de su rol: las solicitudes de cambio de teléfono. Puede apagar el correo; en la plataforma los seguirá viendo en la **campana**.

> [!example] Captura pendiente
> Pantalla: ingresar al sistema.

## 2. Atender las solicitudes de cambio de teléfono
*Requisitos: [[RF27]]*

Un estudiante solo puede marcar asistencia desde **un** teléfono registrado. Cuando cambia o pierde el suyo, pide el cambio y usted lo autoriza.

1. Le llega un aviso, y en el menú aparece un número junto a **Solicitudes de dispositivo**. Ábralo en **Pendientes**.
2. Lea el **motivo**. Si el estudiante ya cambió de teléfono en los últimos 30 días, verá si adjuntó una justificación. En **Historial de teléfonos** están sus registros anteriores.
3. Si necesita más información, pulse **Ver su diagnóstico**: abre sus intentos de asistencia.
4. Pulse **Aprobar**: se revoca el teléfono anterior y el estudiante puede registrar el nuevo. O pulse **Rechazar** y escriba el **motivo**, que es lo que le llega al estudiante.
5. En **Resueltas** queda quién resolvió cada solicitud, cuándo y el motivo de un rechazo. Si hay varias personas en Soporte, al resolver una solicitud el resto recibe un aviso para no atenderla dos veces.

> [!info] No hace falta verificar la identidad
> La solicitud viene de la sesión de Google del propio estudiante, con su correo @ues.edu.sv.

> [!example] Captura pendiente
> Pantalla: atender las solicitudes de cambio de teléfono.

## 3. Averiguar por qué alguien no pudo marcar asistencia
*Requisitos: [[RF20]]*

1. Abra **Diagnóstico de asistencia**. Verá los intentos de hoy, con un resumen arriba (por ejemplo, cuántos quedaron **Fuera del área**).
2. Busque al estudiante por **correo, carné o nombre**. Basta el inicio de cualquiera de sus palabras: «jan» encuentra a «Jan Carlos Pérez».
3. Para otros días, use **Desde** y **Hasta**. **Limpiar filtros** vuelve a hoy.
4. Con **Resultado** se queda con un motivo: código vencido, fuera del área, dispositivo inválido, sin ubicación… Con **Solo los rechazos** oculta los registros correctos.
5. Con **Exportar** descarga en Excel o CSV lo que muestran los filtros. Nunca sale la ubicación del estudiante, solo su distancia al área.
6. Es solo de consulta: desde aquí no se cambia la asistencia. Si el problema fue de la plataforma, avise al docente para que corrija el registro.

| Resultado | Qué significa | Qué decirle al estudiante |
|---|---|---|
| Código vencido | Escaneó un QR viejo, por ejemplo una foto | Que escanee el código que está en pantalla |
| Fuera del área | Su ubicación quedó fuera de las áreas del campus | Que active el GPS y se acerque; si el aula está fuera del área, avise al Administrador |
| Dispositivo inválido | Usó un teléfono que no es el registrado | Que use su teléfono o pida el cambio |
| Sin ubicación | El navegador no dio la ubicación | Que permita la ubicación al navegador |
| Sin intentos | Ya usó sus 3 intentos de la sesión | Que pida un código temporal a su docente |

> [!example] Captura pendiente
> Pantalla: averiguar por qué alguien no pudo marcar asistencia.

## 4. Crear y corregir cuentas de estudiantes
*Requisitos: [[RF04]] · [[ADR-037 Jerarquía de creación y asignación de roles]]*

1. Abra **Usuarios**. Puede filtrar por rol, estado y **Teléfono de asistencia**. Con **Estudiantes sin teléfono** sabe quiénes todavía no pueden marcar con QR, con su correo para escribirles.
2. Con **Nuevo usuario** crea cuentas de **estudiante**. Solo puede crear docentes si el Administrador le dio ese permiso; nunca administradores, jefes ni coordinadores.
3. Entre a una cuenta para ver y corregir sus datos con **Editar datos**. El correo institucional no se puede cambiar.
4. **Desactivar una cuenta es solo del Administrador.** Si hay que quitarle el acceso a alguien, escríbale.

> [!example] Captura pendiente
> Pantalla: crear y corregir cuentas de estudiantes.

## 5. Importar estudiantes desde un archivo
*Requisitos: [[RF04]] · [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]*

1. Abra **Importar estudiantes** y pulse **Descargar plantilla (CSV)**.
2. Llénela con carné, nombres, apellidos, correo, rol, carrera, plan y ciclo. Si la llena en Excel, use **Guardar como → CSV UTF-8**. Solo se aceptan correos **@ues.edu.sv**.
3. Suba el archivo y revise la **vista previa**: cuántas cuentas se crearán, cuántos duplicados se omiten y qué filas tienen error, con su motivo.
4. Confirme. En **Lotes anteriores** queda cada importación con sus filas creadas y con error.

> [!example] Captura pendiente
> Pantalla: importar estudiantes desde un archivo.

## Problemas frecuentes
| Si le pasa esto… | Haga esto |
|---|---|
| Un estudiante borró los datos del navegador y perdió su registro | Que pida el cambio de teléfono, aunque sea el mismo; usted lo aprueba |
| La importación rechaza filas por la carrera | Revise que el código de carrera y el plan existan en el catálogo |
| Un docente no tiene cuenta | Si no tiene el permiso de crear docentes, pida la cuenta al Administrador |
