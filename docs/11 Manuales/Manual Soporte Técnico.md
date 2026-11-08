---
tipo: manual
rol: "[[Soporte Técnico]]"
ayuda_ruta: /ayuda/soporte-tecnico
estado: esqueleto
---

# Manual del Soporte Técnico

Esqueleto por tareas, redactado de **usted**. Las capturas se agregan cuando exista la interfaz. Se publica en la app en `/ayuda/soporte-tecnico` ([[Ayuda en la app]]).

## 1. Ingresar al sistema
*Requisitos: [[RF01]]*

1. Abra la plataforma y pulse **Iniciar sesión con Google**.
2. Elija su cuenta institucional **@ues.edu.sv**. Otras cuentas son rechazadas.
3. Si aparece «Su cuenta no está habilitada», contacte al Administrador: su usuario aún no fue dado de alta.
4. Su sesión se cierra sola tras 30 minutos sin actividad. Desde **Mi perfil → Sesiones** puede cerrar las sesiones abiertas en otros equipos.
5. En **Mi perfil → Notificaciones** solo aparecen los avisos que su rol puede recibir: los de las solicitudes de cambio de teléfono. Puede apagar el correo de cada uno; en la plataforma los seguirá viendo en la campana.

> [!example] Captura pendiente
> Pantalla: ingresar al sistema.

## 2. Atender solicitudes de cambio de teléfono
*Requisitos: [[RF27]]*

1. Cuando un estudiante pide el cambio le llega un **aviso** (campana y correo) y el menú suma esa solicitud al número junto a **Solicitudes de dispositivo**. Ábralo en **Pendientes**: la identidad ya la garantiza la sesión de Google del estudiante.
2. Revise el motivo y, si hubo un cambio en los últimos 30 días, la justificación adjunta.
3. Si necesita cruzar información, pulse **Ver su diagnóstico**: abre el diagnóstico de asistencia ya filtrado por ese estudiante.
4. Pulse **Aprobar** (revoca el dispositivo anterior) o **Rechazar**. Las dos acciones piden confirmación, y para rechazar el **motivo es obligatorio**: es lo que le llega al estudiante.
5. En **Resueltas** queda el registro: quién la resolvió, cuándo y, si fue rechazada, el motivo que escribió. Si son varias personas en la bandeja, al cerrar una solicitud el resto del equipo recibe el aviso para no trabajarla de nuevo.

> [!example] Captura pendiente
> Pantalla: atender solicitudes de cambio de teléfono.

## 3. Revisar el diagnóstico de asistencia
*Requisitos: [[RF20]]*

1. Al abrir **Diagnóstico de asistencia** verá los rechazos de hoy. Busque al estudiante por **correo, carné o nombre**: al buscar por nombre basta el inicio de cualquiera de sus palabras («jan» encuentra a «Jan Carlos Pérez»).
2. Para ver otros días, fije el **rango de fechas**; con **Hoy** vuelve al día en curso y con **Limpiar filtros**, al inicio.
3. Con el selector de **resultado** se queda solo con un motivo: código vencido, fuera del área, dispositivo inválido, sin ubicación, sin intentos…
4. **Exportar CSV** descarga lo que muestran los filtros. Nunca sale la ubicación del estudiante: solo la distancia al área.
5. Es solo lectura: no puede cambiar asistencia ni matrículas.

> [!example] Captura pendiente
> Pantalla: revisar el diagnóstico de asistencia.

## 4. Crear y corregir cuentas de estudiantes
*Requisitos: [[RF04]] · [[ADR-037 Jerarquía de creación y asignación de roles]]*

1. Abra **Usuarios**. Los filtros son rol, estado y **teléfono de asistencia**: con «Estudiantes sin teléfono» obtiene a quienes todavía no pueden marcar con QR, con su correo para escribirles. Es el detalle de la tarjeta del mismo nombre de su Inicio.
2. Con **Nuevo usuario** crea cuentas de **estudiante**. Solo podrá crear docentes si el Administrador le concedió el permiso `usuarios.crear-docente`; nunca administradores, jefes ni coordinadores.
3. Entre a una cuenta para ver y corregir sus datos, roles y estado. **Desactivar una cuenta es solo del Administrador**: si hay que cerrar un acceso, escríbale.

> [!example] Captura pendiente
> Pantalla: crear y corregir cuentas.

## 5. Importar estudiantes
*Requisitos: [[RF04]] · [[ADR-047 Carreras visibles y carga masiva sin el Coordinador]]*

1. Abra **Importar estudiantes**. Por ahora el archivo se sube en **CSV** (en Excel: Guardar como → CSV UTF-8); solo correos **@ues.edu.sv**.
2. Revise la **vista previa**: cuántas cuentas se crearán, cuántos duplicados se omiten y qué filas traen error, con su motivo.
3. Confirme la creación. En **Lotes anteriores** queda cada importación con sus filas creadas y con error.

> [!example] Captura pendiente
> Pantalla: importar estudiantes.
