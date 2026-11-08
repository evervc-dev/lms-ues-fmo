<?php

/*
|--------------------------------------------------------------------------
| Menú unificado (ADR-034, docs/13 Layout/Layout base.md)
|--------------------------------------------------------------------------
|
| Un grupo con «rol» solo se muestra a quien tiene ese rol; cada opción, a
| quien tiene su permiso. Una opción con la misma «clave» en dos grupos se
| muestra una sola vez, en el primero. Las rutas que aún no existen se
| muestran deshabilitadas («Pronto»).
|
| «contador» es la clave de App\Support\Navegacion\Contadores con la que se
| calcula el número de pendientes que acompaña a la opción. «secundaria» la
| dibuja con sangría bajo la opción anterior, de la que es una acción, y
| «externa» la abre con una carga normal, sin wire:navigate.
|
*/

return [

    'grupos' => [
        [
            'titulo' => null,
            'rol' => null,
            'opciones' => [
                ['clave' => 'inicio', 'etiqueta' => 'Inicio', 'ruta' => 'inicio', 'icono' => 'home'],
            ],
        ],
        [
            'titulo' => 'Estudio',
            'rol' => 'estudiante',
            'opciones' => [
                ['clave' => 'escanear-qr', 'etiqueta' => 'Escanear QR', 'ruta' => 'asistencia.escanear', 'permiso' => 'asistencia.marcar', 'icono' => 'qr-code'],
                ['clave' => 'inscripcion', 'etiqueta' => 'Inscripción de materias', 'ruta' => 'inscripcion', 'permiso' => 'matricula.automatricular', 'icono' => 'user-plus'],
                ['clave' => 'mis-materias', 'etiqueta' => 'Mis materias', 'ruta' => 'materias', 'permiso' => 'contenidos.ver', 'icono' => 'book-open'],
                ['clave' => 'mis-notas', 'etiqueta' => 'Mis notas', 'ruta' => 'mis-notas', 'permiso' => 'calificaciones.ver', 'icono' => 'chart-bar'],
                ['clave' => 'mi-asistencia', 'etiqueta' => 'Mi asistencia', 'ruta' => 'mi-asistencia', 'permiso' => 'asistencia.ver', 'icono' => 'clipboard-document-check'],
            ],
        ],
        [
            'titulo' => 'Docencia',
            'rol' => 'docente',
            'opciones' => [
                ['clave' => 'mis-secciones', 'etiqueta' => 'Mis secciones', 'ruta' => 'secciones', 'parametros' => ['ambito' => 'propias'], 'permiso' => 'contenidos.gestionar', 'icono' => 'academic-cap'],
                ['clave' => 'pendientes-de-calificar', 'etiqueta' => 'Pendientes de calificar', 'ruta' => 'pendientes-de-calificar', 'permiso' => 'calificaciones.gestionar', 'icono' => 'inbox-stack'],
                ['clave' => 'bancos-de-preguntas', 'etiqueta' => 'Bancos de preguntas', 'ruta' => 'bancos-de-preguntas', 'permiso' => 'banco-preguntas.gestionar', 'icono' => 'rectangle-stack'],
            ],
        ],
        [
            'titulo' => 'Coordinación',
            'rol' => 'coordinador',
            'opciones' => [
                ['clave' => 'carreras', 'etiqueta' => 'Carreras', 'ruta' => 'carreras', 'permiso' => 'carreras.ver', 'icono' => 'academic-cap'],
                ['clave' => 'supervision-de-aulas', 'etiqueta' => 'Supervisión de aulas', 'ruta' => 'secciones', 'parametros' => ['ambito' => 'carrera'], 'permiso' => 'contenidos.ver', 'icono' => 'eye'],
                ['clave' => 'carga-academica', 'etiqueta' => 'Carga académica', 'ruta' => 'carga-academica', 'permiso' => 'secciones.gestionar', 'icono' => 'building-office'],
                ['clave' => 'matricula', 'etiqueta' => 'Matrícula', 'ruta' => 'matricula', 'permiso' => 'matricula.gestionar', 'icono' => 'user-plus'],
                ['clave' => 'actas', 'etiqueta' => 'Actas por validar', 'ruta' => 'actas', 'permiso' => 'actas.validar', 'icono' => 'clipboard-document-list'],
                ['clave' => 'calendario-evaluaciones', 'etiqueta' => 'Calendario de evaluaciones', 'ruta' => 'periodos.calendario', 'permiso' => 'periodos.calendario.gestionar', 'icono' => 'calendar-days'],
                ['clave' => 'clases-canceladas', 'etiqueta' => 'Clases canceladas', 'ruta' => 'clases-canceladas', 'permiso' => 'clases.cancelar', 'icono' => 'x-circle'],
            ],
        ],
        [
            'titulo' => 'Jefatura',
            'rol' => 'jefe_departamento',
            'opciones' => [
                ['clave' => 'carreras', 'etiqueta' => 'Carreras', 'ruta' => 'carreras', 'permiso' => 'carreras.ver', 'icono' => 'academic-cap'],
                ['clave' => 'secciones-del-departamento', 'etiqueta' => 'Secciones del departamento', 'ruta' => 'secciones', 'parametros' => ['ambito' => 'departamento'], 'permiso' => 'matricula.gestionar', 'icono' => 'building-library'],
                ['clave' => 'carga-academica', 'etiqueta' => 'Carga académica', 'ruta' => 'carga-academica', 'permiso' => 'secciones.asignar-docente', 'icono' => 'building-office'],
                ['clave' => 'usuarios', 'etiqueta' => 'Usuarios', 'ruta' => 'usuarios', 'permiso' => 'usuarios.ver', 'icono' => 'users'],
                ['clave' => 'claves-matricula', 'etiqueta' => 'Claves de matrícula', 'ruta' => 'matricula.claves', 'permiso' => 'matricula.claves.gestionar', 'icono' => 'user-plus'],
                ['clave' => 'plazos-matricula', 'etiqueta' => 'Plazos de matrícula', 'ruta' => 'matricula.plazos', 'permiso' => 'matricula.plazo.gestionar', 'icono' => 'calendar-days'],
                ['clave' => 'cierre', 'etiqueta' => 'Cierre del período', 'ruta' => 'cierre', 'permiso' => 'cierre.autorizar', 'icono' => 'lock-closed'],
                ['clave' => 'calendario-evaluaciones', 'etiqueta' => 'Calendario de evaluaciones', 'ruta' => 'periodos.calendario', 'permiso' => 'periodos.calendario.gestionar', 'icono' => 'calendar-days'],
                ['clave' => 'desempeno-docente', 'etiqueta' => 'Desempeño docente', 'ruta' => 'desempeno-docente', 'permiso' => 'desempeno-docente.ver', 'icono' => 'chart-pie'],
            ],
        ],
        [
            'titulo' => 'Administración',
            'rol' => 'administrador',
            'opciones' => [
                ['clave' => 'usuarios', 'etiqueta' => 'Usuarios', 'ruta' => 'usuarios', 'permiso' => 'usuarios.gestionar', 'icono' => 'users'],
                ['clave' => 'importar-estudiantes', 'etiqueta' => 'Importar estudiantes', 'ruta' => 'usuarios.importar', 'permiso' => 'usuarios.importar', 'icono' => 'arrow-up-tray', 'secundaria' => true],
                ['clave' => 'roles', 'etiqueta' => 'Roles y permisos', 'ruta' => 'roles', 'permiso' => 'roles.gestionar', 'icono' => 'shield-check'],
                ['clave' => 'periodos', 'etiqueta' => 'Períodos', 'ruta' => 'periodos', 'permiso' => 'periodos.gestionar', 'icono' => 'calendar-days'],
                ['clave' => 'calendario-evaluaciones', 'etiqueta' => 'Calendario de evaluaciones', 'ruta' => 'periodos.calendario', 'permiso' => 'periodos.calendario.gestionar', 'icono' => 'calendar-days'],
                ['clave' => 'catalogo', 'etiqueta' => 'Catálogo', 'ruta' => 'catalogo', 'permiso' => 'asignaturas.gestionar', 'icono' => 'squares-2x2'],
                ['clave' => 'carga-academica', 'etiqueta' => 'Carga académica', 'ruta' => 'carga-academica', 'permiso' => 'secciones.gestionar', 'icono' => 'building-office'],
                ['clave' => 'matricula', 'etiqueta' => 'Matrícula', 'ruta' => 'matricula', 'permiso' => 'matricula.gestionar', 'icono' => 'user-plus'],
                ['clave' => 'areas-asistencia', 'etiqueta' => 'Áreas de asistencia', 'ruta' => 'areas-asistencia', 'permiso' => 'areas-asistencia.gestionar', 'icono' => 'map-pin'],
                ['clave' => 'clases-canceladas', 'etiqueta' => 'Clases canceladas', 'ruta' => 'clases-canceladas', 'permiso' => 'clases.cancelar', 'icono' => 'x-circle'],
                ['clave' => 'integraciones', 'etiqueta' => 'Integraciones', 'ruta' => 'integraciones', 'permiso' => 'integraciones.gestionar', 'icono' => 'puzzle-piece'],
                ['clave' => 'colas', 'etiqueta' => 'Monitoreo técnico', 'ruta' => 'horizon.index', 'permiso' => 'horizon.ver', 'icono' => 'queue-list', 'externa' => true],
            ],
        ],
        [
            'titulo' => 'Soporte',
            'rol' => 'soporte_tecnico',
            'opciones' => [
                ['clave' => 'solicitudes-de-dispositivo', 'etiqueta' => 'Solicitudes de dispositivo', 'ruta' => 'dispositivos', 'permiso' => 'dispositivos.gestionar', 'icono' => 'device-phone-mobile', 'contador' => 'dispositivos-pendientes'],
                ['clave' => 'diagnostico-de-asistencia', 'etiqueta' => 'Diagnóstico de asistencia', 'ruta' => 'asistencia.diagnostico', 'permiso' => 'asistencia.diagnostico.ver', 'icono' => 'signal'],
                ['clave' => 'usuarios', 'etiqueta' => 'Usuarios', 'ruta' => 'usuarios', 'permiso' => 'usuarios.ver', 'icono' => 'users'],
                ['clave' => 'importar-estudiantes', 'etiqueta' => 'Importar estudiantes', 'ruta' => 'usuarios.importar', 'permiso' => 'usuarios.importar', 'icono' => 'arrow-up-tray', 'secundaria' => true],
            ],
        ],
        [
            'titulo' => 'Consultas',
            'rol' => null,
            'opciones' => [
                ['clave' => 'reportes', 'etiqueta' => 'Reportes', 'ruta' => 'reportes', 'permiso' => 'reportes.ver', 'icono' => 'document-chart-bar'],
                ['clave' => 'oferta', 'etiqueta' => 'Estadísticas de oferta', 'ruta' => 'oferta', 'permiso' => 'oferta.estadisticas.ver', 'icono' => 'presentation-chart-line'],
                ['clave' => 'auditoria', 'etiqueta' => 'Auditoría', 'ruta' => 'auditoria', 'permiso' => 'auditoria.ver', 'icono' => 'finger-print'],
            ],
        ],
        [
            'titulo' => 'Cuenta',
            'rol' => null,
            'opciones' => [
                ['clave' => 'perfil', 'etiqueta' => 'Mi perfil', 'ruta' => 'perfil', 'icono' => 'user-circle'],
                ['clave' => 'ayuda', 'etiqueta' => 'Ayuda', 'ruta' => 'ayuda', 'icono' => 'question-mark-circle'],
            ],
        ],
    ],

];
