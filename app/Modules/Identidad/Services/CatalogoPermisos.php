<?php

namespace App\Modules\Identidad\Services;

/**
 * Nombre legible y descripción de cada permiso (RF03), para la pantalla de Roles y permisos. La clave técnica de spatie
 * se sigue mostrando, en gris, para quien la necesite. Un permiso sin entrada aquí muestra su clave.
 */
final class CatalogoPermisos
{
    /** @var array<string, array{0: string, 1: string}> clave => [nombre, descripción] */
    private const PERMISOS = [
        'usuarios.ver' => ['Ver usuarios', 'Consultar la lista y la ficha de las cuentas.'],
        'perfiles.ver' => ['Ver perfiles', 'Ver el perfil de las personas con las que se relaciona.'],
        'usuarios.gestionar' => ['Gestionar usuarios', 'Crear cuentas, editar sus datos y desactivarlas.'],
        'usuarios.crear-docente' => ['Crear docentes', 'Permiso extra de Soporte para crear cuentas de docente.'],
        'usuarios.roles.asignar' => ['Asignar roles', 'Dar Docente y Coordinador en su departamento.'],
        'usuarios.importar' => ['Importar usuarios', 'Cargar cuentas desde un archivo CSV.'],
        'roles.gestionar' => ['Gestionar roles y permisos', 'Conceder o quitar permisos a cada rol en esta pantalla.'],
        'integraciones.gestionar' => ['Gestionar integraciones', 'Configurar Google, el correo y otros servicios externos.'],
        'horizon.ver' => ['Ver el monitoreo técnico', 'Revisar las colas de trabajos en segundo plano.'],
        'auditoria.ver' => ['Ver la auditoría', 'Consultar quién cambió qué y cuándo.'],
        'dispositivo.registrar' => ['Registrar su teléfono', 'Registrar el teléfono con el que marca asistencia.'],
        'dispositivo.solicitar-cambio' => ['Solicitar cambio de teléfono', 'Pedir a Soporte que cambie el teléfono registrado.'],
        'dispositivos.gestionar' => ['Gestionar teléfonos', 'Aprobar o rechazar cambios de teléfono de los estudiantes.'],
        'periodos.gestionar' => ['Gestionar períodos', 'Crear, preparar, abrir y cerrar períodos académicos.'],
        'periodos.calendario.gestionar' => ['Gestionar el calendario de evaluaciones', 'Agregar fechas del período y sincronizarlas con Google Calendar.'],
        'periodos.calendario.ver' => ['Ver el calendario de evaluaciones', 'Consultar las fechas de evaluación que le aplican, sin cambiarlas.'],
        'asignaturas.gestionar' => ['Gestionar el catálogo', 'Crear, editar, desactivar y eliminar asignaturas.'],
        'carreras.ver' => ['Ver carreras', 'Consultar carreras con su coordinador, materias y grupos.'],
        'secciones.crear' => ['Crear grupos teóricos', 'Abrir grupos teóricos en un período.'],
        'secciones.gestionar' => ['Gestionar grupos teóricos', 'Editar horarios y aulas de los grupos.'],
        'secciones.asignar-docente' => ['Asignar docentes', 'Asignar o cambiar el docente de un grupo teórico.'],
        'secciones.cupo' => ['Cambiar el cupo', 'Ajustar el cupo mínimo y máximo de un grupo.'],
        'matricula.gestionar' => ['Gestionar la matrícula', 'Inscribir y retirar estudiantes de los grupos.'],
        'matricula.cambio.resolver' => ['Resolver cambios de grupo', 'Aprobar o rechazar solicitudes de cambio de grupo.'],
        'matricula.claves.gestionar' => ['Gestionar claves de matrícula', 'Generar las claves con las que se inscriben los estudiantes.'],
        'matricula.automatricular' => ['Inscribirse en materias', 'Inscribirse con la clave de la asignatura.'],
        'matricula.plazo.gestionar' => ['Gestionar plazos de inscripción', 'Fijar la fecha límite de inscripción.'],
        'matricula.plazo.prorrogar' => ['Prorrogar el plazo', 'Extender la fecha límite de inscripción del departamento.'],
        'matricula.prorroga.otorgar' => ['Otorgar prórrogas', 'Dar más tiempo de inscripción a un estudiante.'],
        'matricula.solicitar-cambio' => ['Solicitar cambio de grupo', 'Pedir pasar a otro grupo de la misma materia.'],
        'oferta.estadisticas.ver' => ['Ver estadísticas de oferta', 'Inscritos, aprobados y grupos propuestos por asignatura.'],
        'areas-asistencia.gestionar' => ['Gestionar áreas de asistencia', 'Definir dónde vale marcar asistencia con QR.'],
        'contenidos.ver' => ['Ver contenidos', 'Abrir los materiales de los grupos.'],
        'contenidos.gestionar' => ['Gestionar contenidos', 'Crear unidades y subir materiales.'],
        'contenidos.reutilizar' => ['Reutilizar contenidos', 'Copiar materiales de un período anterior.'],
        'contenidos.analitica.ver' => ['Ver analítica de contenidos', 'Ver quién abrió y completó cada material.'],
        'tareas.gestionar' => ['Gestionar tareas', 'Crear tareas y calificar entregas.'],
        'tareas.entregar' => ['Entregar tareas', 'Subir archivos como entrega de una tarea.'],
        'banco-preguntas.gestionar' => ['Gestionar bancos de preguntas', 'Crear y compartir bancos de preguntas.'],
        'cuestionarios.gestionar' => ['Gestionar cuestionarios', 'Crear cuestionarios y seguir los intentos en vivo.'],
        'cuestionarios.resolver' => ['Resolver cuestionarios', 'Responder los cuestionarios de sus materias.'],
        'foros.ver' => ['Ver foros', 'Leer hilos y respuestas sin participar.'],
        'foros.participar' => ['Participar en foros', 'Publicar y responder en el foro del grupo.'],
        'foros.moderar' => ['Moderar foros', 'Ocultar mensajes y cerrar o fijar hilos.'],
        'calificaciones.ver' => ['Ver calificaciones', 'Consultar notas.'],
        'calificaciones.gestionar' => ['Gestionar calificaciones', 'Poner y publicar notas.'],
        'calificaciones.revision.solicitar' => ['Solicitar revisión de nota', 'Pedir que se revise una calificación.'],
        'calificaciones.revision.resolver' => ['Resolver revisiones', 'Responder solicitudes de revisión de notas.'],
        'calificaciones.revision.ver' => ['Ver revisiones', 'Consultar las solicitudes de revisión de notas.'],
        'actas.publicar' => ['Publicar actas', 'Publicar el acta de notas de su grupo.'],
        'actas.validar' => ['Validar actas', 'Revisar y validar las actas publicadas.'],
        'cierre.autorizar' => ['Autorizar el cierre', 'Autorizar el cierre del período en su departamento.'],
        'calificaciones.corregir' => ['Corregir calificaciones', 'Corregir una nota ya publicada, con motivo.'],
        'progreso.ver' => ['Ver progreso', 'Ver el avance en los materiales.'],
        'asistencia.sesion.ver' => ['Ver detalle de sesión de asistencia', 'Consultar marcaciones, hora de apertura y cierre, sin poder operar.'],
        'asistencia.sesion.abrir' => ['Abrir asistencia', 'Abrir la sesión de asistencia con QR.'],
        'asistencia.marcar' => ['Marcar asistencia', 'Escanear el QR o usar un código temporal.'],
        'asistencia.codigo-temporal.generar' => ['Generar códigos temporales', 'Dar un código a quien no puede escanear.'],
        'asistencia.diagnostico.ver' => ['Ver diagnóstico de asistencia', 'Revisar intentos fallidos de marcar asistencia.'],
        'asistencia.corregir' => ['Corregir asistencia', 'Cambiar un registro de asistencia, con motivo.'],
        'asistencia.ver' => ['Ver asistencia', 'Consultar registros y porcentajes de asistencia.'],
        'videoconferencias.gestionar' => ['Gestionar clases virtuales', 'Programar clases en Meet y subir su reporte.'],
        'clases.solicitar-cancelacion' => ['Solicitar cancelación de clase', 'Pedir que se cancele o cambie de modalidad una clase.'],
        'clases.cancelar' => ['Cancelar clases', 'Aprobar o hacer cancelaciones de clase.'],
        'mensajes.condicionales.enviar' => ['Enviar mensajes a estudiantes', 'Escribir a quienes cumplen una condición (p. ej., sin entregar).'],
        'reportes.ver' => ['Ver reportes', 'Consultar y descargar reportes.'],
        'reportes.programar' => ['Programar reportes', 'Recibir reportes por correo de forma periódica.'],
        'desempeno-docente.ver' => ['Ver desempeño docente', 'Indicadores de los docentes del departamento.'],
        'notificaciones.preferencias' => ['Preferencias de notificación', 'Elegir qué avisos recibe por correo.'],
        'perfil.sesiones.cerrar' => ['Cerrar sus sesiones', 'Cerrar la sesión abierta en otros equipos.'],
    ];

    /** @var array<string, string> prefijo de la clave => nombre del grupo */
    private const GRUPOS = [
        'usuarios' => 'Usuarios', 'perfiles' => 'Perfiles', 'roles' => 'Roles y permisos', 'integraciones' => 'Integraciones',
        'horizon' => 'Monitoreo técnico', 'auditoria' => 'Auditoría', 'dispositivo' => 'Teléfono del estudiante',
        'dispositivos' => 'Teléfonos de los estudiantes', 'periodos' => 'Períodos', 'asignaturas' => 'Catálogo', 'carreras' => 'Carreras',
        'secciones' => 'Grupos teóricos', 'matricula' => 'Matrícula', 'oferta' => 'Oferta académica', 'areas-asistencia' => 'Áreas de asistencia',
        'contenidos' => 'Contenidos', 'tareas' => 'Tareas', 'banco-preguntas' => 'Bancos de preguntas', 'cuestionarios' => 'Cuestionarios',
        'foros' => 'Foros', 'calificaciones' => 'Calificaciones', 'actas' => 'Actas', 'cierre' => 'Cierre del período', 'progreso' => 'Progreso',
        'asistencia' => 'Asistencia', 'videoconferencias' => 'Clases virtuales', 'clases' => 'Clases', 'mensajes' => 'Mensajes',
        'reportes' => 'Reportes', 'desempeno-docente' => 'Desempeño docente', 'notificaciones' => 'Notificaciones', 'perfil' => 'Mi perfil',
    ];

    public static function grupo(string $prefijo): string
    {
        return __(self::GRUPOS[$prefijo] ?? $prefijo);
    }

    /**
     * @return array{nombre: string, descripcion: string}
     */
    public static function de(string $permiso): array
    {
        $datos = self::PERMISOS[$permiso] ?? null;

        return ['nombre' => $datos === null ? $permiso : __($datos[0]), 'descripcion' => $datos === null ? '' : __($datos[1])];
    }
}
