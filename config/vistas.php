<?php

/*
|--------------------------------------------------------------------------
| Registro de vistas (ADR-035, docs/13 Layout/Mapa de vistas.md)
|--------------------------------------------------------------------------
|
| Fuente única de las rutas de la aplicación. Cada vista que todavía no se
| implementa se registra sola como página «En construcción», con su permiso,
| sus migas y su maqueta. Al implementarla, el equipo define en su módulo una
| ruta con el MISMO nombre (Route::livewire) y la provisional desaparece.
|
| Campos: uri, permiso (uno o varios con «|»; null = solo sesión iniciada),
| titulo, modulo, rf, op (tarea Front), maqueta (docs/13 Layout/Maquetas),
| layout (app|exam|projection) y seccion (true = pestaña de la Vista de sección).
|
*/

return [

    // Comunes
    'notificaciones' => ['uri' => '/notificaciones', 'permiso' => null, 'titulo' => 'Notificaciones', 'modulo' => 'Comunicacion', 'rf' => ['RF23'], 'op' => 463],
    'perfil' => ['uri' => '/perfil', 'permiso' => null, 'titulo' => 'Mi perfil', 'modulo' => 'Identidad', 'rf' => ['RF01'], 'op' => 379, 'maqueta' => '16 Mi perfil'],
    'perfil.dispositivo' => ['uri' => '/perfil/dispositivo', 'permiso' => 'dispositivo.registrar', 'titulo' => 'Dispositivo de asistencia', 'modulo' => 'Identidad', 'rf' => ['RF27'], 'op' => 487, 'maqueta' => '16 Mi perfil', 'migas' => ['perfil']],
    'perfil.google-calendar' => ['uri' => '/perfil/google-calendar', 'permiso' => 'videoconferencias.gestionar|periodos.gestionar|periodos.calendario.gestionar', 'titulo' => 'Google Calendar', 'modulo' => 'Asistencia', 'rf' => ['RF21', 'RF06'], 'op' => 455, 'migas' => ['perfil'], 'maqueta' => '52 Mi perfil · Google Calendar'],
    'perfil.notificaciones' => ['uri' => '/perfil/notificaciones', 'permiso' => 'notificaciones.preferencias', 'titulo' => 'Preferencias de notificación', 'modulo' => 'Comunicacion', 'rf' => ['RF23'], 'op' => 463, 'migas' => ['perfil'], 'maqueta' => '16 Mi perfil'],
    'perfil.sesiones' => ['uri' => '/perfil/sesiones', 'permiso' => 'perfil.sesiones.cerrar', 'titulo' => 'Sesiones', 'modulo' => 'Identidad', 'rf' => ['RF01'], 'op' => 379, 'migas' => ['perfil'], 'maqueta' => '16 Mi perfil'],
    'ayuda' => ['uri' => '/ayuda/{manual?}', 'permiso' => null, 'titulo' => 'Ayuda', 'modulo' => 'Comunicacion', 'rf' => [], 'op' => null, 'maqueta' => '49 Ayuda · manual del docente'],

    // Estudiante
    'asistencia.escanear' => ['uri' => '/asistencia/escanear', 'permiso' => 'asistencia.marcar', 'titulo' => 'Escanear QR', 'modulo' => 'Asistencia', 'rf' => ['RF20', 'RF28'], 'op' => 451, 'maqueta' => '04 Escanear QR (móvil)'],
    'inscripcion' => ['uri' => '/inscripcion', 'permiso' => 'matricula.automatricular', 'titulo' => 'Inscripción de materias', 'modulo' => 'Academica', 'rf' => ['RF29'], 'op' => 495],
    'inscripcion.show' => ['uri' => '/inscripcion/{asignatura}', 'permiso' => 'matricula.automatricular', 'titulo' => 'Materia', 'modulo' => 'Academica', 'rf' => ['RF29'], 'op' => 495, 'migas' => ['inscripcion']],
    'materias' => ['uri' => '/materias', 'permiso' => 'matricula.automatricular', 'titulo' => 'Mis materias', 'modulo' => 'Academica', 'rf' => ['RF18', 'RF29'], 'op' => 495, 'maqueta' => '05 Mis materias'],
    'mis-notas' => ['uri' => '/mis-notas', 'permiso' => 'calificaciones.ver', 'titulo' => 'Mis notas', 'modulo' => 'Calificaciones', 'rf' => ['RF17'], 'op' => 439, 'maqueta' => '11 Mis notas'],
    'mi-asistencia' => ['uri' => '/mi-asistencia', 'permiso' => 'asistencia.marcar', 'titulo' => 'Mi asistencia', 'modulo' => 'Asistencia', 'rf' => ['RF22'], 'op' => 459, 'maqueta' => '13 Mi asistencia'],
    'intentos.show' => ['uri' => '/intentos/{intento}', 'permiso' => 'cuestionarios.resolver', 'titulo' => 'Intento de cuestionario', 'modulo' => 'Evaluaciones', 'rf' => ['RF16'], 'op' => 435, 'maqueta' => '10 Layout Examen · intento', 'layout' => 'exam'],

    // Secciones y Vista de sección (una página por pestaña)
    'secciones' => ['uri' => '/secciones', 'permiso' => 'contenidos.gestionar|contenidos.analitica.ver|cierre.autorizar', 'titulo' => 'Secciones', 'modulo' => 'Academica', 'rf' => ['RF08'], 'op' => 403],
    'secciones.contenidos' => ['uri' => '/secciones/{seccion}', 'permiso' => 'contenidos.ver', 'titulo' => 'Contenidos', 'modulo' => 'Contenidos', 'rf' => ['RF11'], 'op' => 415, 'maqueta' => '06 Vista de sección · Contenidos', 'seccion' => true],
    'secciones.recurso' => ['uri' => '/secciones/{seccion}/recursos/{recurso}', 'permiso' => 'contenidos.ver', 'titulo' => 'Recurso', 'modulo' => 'Contenidos', 'rf' => ['RF11', 'RF12'], 'op' => 419, 'seccion' => true, 'pestana' => 'secciones.contenidos'],
    'secciones.recursos.crear' => ['uri' => '/secciones/{seccion}/unidades/{unidad}/recursos/crear', 'permiso' => 'contenidos.gestionar', 'titulo' => 'Agregar recurso', 'modulo' => 'Contenidos', 'rf' => ['RF11', 'RF12'], 'op' => 419, 'seccion' => true, 'pestana' => 'secciones.contenidos', 'donde' => ['unidad' => '[0-9]+']],
    'secciones.papelera' => ['uri' => '/secciones/{seccion}/papelera', 'permiso' => 'contenidos.gestionar', 'titulo' => 'Papelera', 'modulo' => 'Contenidos', 'rf' => ['RF11'], 'op' => 415, 'seccion' => true, 'pestana' => 'secciones.contenidos'],
    'secciones.importar' => ['uri' => '/secciones/{seccion}/importar', 'permiso' => 'contenidos.reutilizar', 'titulo' => 'Importar de un período anterior', 'modulo' => 'Contenidos', 'rf' => ['RF14'], 'op' => 427, 'seccion' => true, 'pestana' => 'secciones.contenidos', 'maqueta' => '39 Importar de un período anterior'],
    'secciones.actividades' => ['uri' => '/secciones/{seccion}/actividades', 'permiso' => 'contenidos.ver', 'titulo' => 'Actividades', 'modulo' => 'Evaluaciones', 'rf' => ['RF15', 'RF16'], 'op' => 431, 'seccion' => true],
    'secciones.tareas.crear' => ['uri' => '/secciones/{seccion}/tareas/crear', 'permiso' => 'tareas.gestionar', 'titulo' => 'Nueva tarea', 'modulo' => 'Evaluaciones', 'rf' => ['RF15'], 'op' => 431, 'maqueta' => '19 Crear o editar tarea', 'seccion' => true, 'pestana' => 'secciones.actividades'],
    'secciones.tareas.show' => ['uri' => '/secciones/{seccion}/tareas/{tarea}', 'permiso' => 'tareas.entregar|tareas.gestionar', 'titulo' => 'Tarea', 'modulo' => 'Evaluaciones', 'rf' => ['RF15'], 'op' => 431, 'maqueta' => '08 Tarea · detalle y entrega', 'seccion' => true, 'pestana' => 'secciones.actividades'],
    'secciones.tareas.entregas' => ['uri' => '/secciones/{seccion}/tareas/{tarea}/entregas', 'permiso' => 'calificaciones.gestionar', 'titulo' => 'Entregas', 'modulo' => 'Evaluaciones', 'rf' => ['RF15'], 'op' => 431, 'seccion' => true, 'pestana' => 'secciones.actividades'],
    'secciones.cuestionarios.crear' => ['uri' => '/secciones/{seccion}/cuestionarios/crear', 'permiso' => 'cuestionarios.gestionar', 'titulo' => 'Nuevo cuestionario', 'modulo' => 'Evaluaciones', 'rf' => ['RF16'], 'op' => 435, 'seccion' => true, 'pestana' => 'secciones.actividades'],
    'secciones.cuestionarios.show' => ['uri' => '/secciones/{seccion}/cuestionarios/{cuestionario}', 'permiso' => 'cuestionarios.resolver|cuestionarios.gestionar', 'titulo' => 'Cuestionario', 'modulo' => 'Evaluaciones', 'rf' => ['RF16'], 'op' => 435, 'seccion' => true, 'pestana' => 'secciones.actividades', 'maqueta' => '33 Cuestionario · detalle'],
    'secciones.notas' => ['uri' => '/secciones/{seccion}/notas', 'permiso' => 'calificaciones.ver', 'titulo' => 'Notas', 'modulo' => 'Calificaciones', 'rf' => ['RF17', 'RF18', 'RF31'], 'op' => 439, 'maqueta' => '20 Libro de calificaciones', 'seccion' => true],
    'secciones.pesos' => ['uri' => '/secciones/{seccion}/notas/pesos', 'permiso' => 'calificaciones.gestionar', 'titulo' => 'Pesos de los cómputos', 'modulo' => 'Calificaciones', 'rf' => ['RF17'], 'op' => 439, 'seccion' => true, 'pestana' => 'secciones.notas'],
    'secciones.mensajes' => ['uri' => '/secciones/{seccion}/notas/mensajes', 'permiso' => 'mensajes.condicionales.enviar', 'titulo' => 'Mensajes enviados', 'modulo' => 'Comunicacion', 'rf' => ['RF31'], 'op' => 501, 'seccion' => true, 'pestana' => 'secciones.notas'],
    'secciones.mensajes.crear' => ['uri' => '/secciones/{seccion}/notas/mensajes/crear', 'permiso' => 'mensajes.condicionales.enviar', 'titulo' => 'Mensajear a estudiantes', 'modulo' => 'Comunicacion', 'rf' => ['RF31'], 'op' => 501, 'seccion' => true, 'pestana' => 'secciones.notas'],
    'secciones.revisiones' => ['uri' => '/secciones/{seccion}/revisiones', 'permiso' => 'calificaciones.revision.ver', 'titulo' => 'Revisiones', 'modulo' => 'Calificaciones', 'rf' => ['RF35'], 'op' => 385, 'seccion' => true],
    'secciones.revisiones.show' => ['uri' => '/secciones/{seccion}/revisiones/{revision}', 'permiso' => 'calificaciones.revision.ver', 'titulo' => 'Solicitud de revisión', 'modulo' => 'Calificaciones', 'rf' => ['RF35'], 'op' => 385, 'seccion' => true, 'pestana' => 'secciones.revisiones'],
    'secciones.acta' => ['uri' => '/secciones/{seccion}/acta', 'permiso' => 'actas.publicar', 'titulo' => 'Acta', 'modulo' => 'Calificaciones', 'rf' => ['RF34'], 'op' => 515, 'seccion' => true, 'pestana' => 'secciones.notas', 'maqueta' => '48 Acta de la sección'],
    'secciones.asistencia' => ['uri' => '/secciones/{seccion}/asistencia', 'permiso' => 'asistencia.ver', 'titulo' => 'Asistencia', 'modulo' => 'Asistencia', 'rf' => ['RF19', 'RF22'], 'op' => 447, 'seccion' => true],
    'secciones.asistencia.sesion' => ['uri' => '/secciones/{seccion}/asistencia/{sesion}', 'permiso' => 'asistencia.sesion.abrir', 'titulo' => 'Sesión de asistencia', 'modulo' => 'Asistencia', 'rf' => ['RF19', 'RF21', 'RF28', 'RF32'], 'op' => 447, 'maqueta' => '21 Sesión de asistencia', 'seccion' => true, 'pestana' => 'secciones.asistencia'],
    'secciones.asistencia.proyectar' => ['uri' => '/secciones/{seccion}/asistencia/{sesion}/proyectar', 'permiso' => 'asistencia.sesion.abrir', 'titulo' => 'Proyectar QR', 'modulo' => 'Asistencia', 'rf' => ['RF20'], 'op' => 451, 'maqueta' => '22 Layout Proyección · QR', 'layout' => 'projection'],
    'secciones.foro' => ['uri' => '/secciones/{seccion}/foro', 'permiso' => 'foros.participar', 'titulo' => 'Foro', 'modulo' => 'Comunicacion', 'rf' => ['RF30'], 'op' => 499, 'seccion' => true, 'maqueta' => '40 Foro'],
    'secciones.foro.hilo' => ['uri' => '/secciones/{seccion}/foro/{hilo}', 'permiso' => 'foros.participar', 'titulo' => 'Hilo', 'modulo' => 'Comunicacion', 'rf' => ['RF30'], 'op' => 499, 'seccion' => true, 'pestana' => 'secciones.foro', 'maqueta' => '41 Hilo del foro'],
    'secciones.estudiantes' => ['uri' => '/secciones/{seccion}/estudiantes', 'permiso' => 'matricula.gestionar', 'titulo' => 'Estudiantes', 'modulo' => 'Academica', 'rf' => ['RF09'], 'op' => 407, 'seccion' => true, 'maqueta' => '38 Estudiantes de la sección'],
    'secciones.participantes' => ['uri' => '/secciones/{seccion}/participantes', 'permiso' => 'perfiles.ver', 'titulo' => 'Participantes', 'modulo' => 'Academica', 'rf' => ['RF36'], 'op' => null, 'seccion' => true],
    'secciones.progreso' => ['uri' => '/secciones/{seccion}/progreso', 'permiso' => 'progreso.ver', 'titulo' => 'Progreso', 'modulo' => 'Contenidos', 'rf' => ['RF18'], 'op' => 441, 'seccion' => true],
    'secciones.analitica' => ['uri' => '/secciones/{seccion}/analitica', 'permiso' => 'contenidos.analitica.ver', 'titulo' => 'Analítica', 'modulo' => 'Contenidos', 'rf' => ['RF13'], 'op' => 423, 'seccion' => true, 'maqueta' => '42 Analítica de la sección'],

    // Docencia
    'pendientes-de-calificar' => ['uri' => '/pendientes-de-calificar', 'permiso' => 'calificaciones.gestionar', 'titulo' => 'Pendientes de calificar', 'modulo' => 'Evaluaciones', 'rf' => ['RF15', 'RF16', 'RF24'], 'op' => 431, 'maqueta' => '18 Pendientes de calificar'],
    'bancos-de-preguntas' => ['uri' => '/bancos-de-preguntas', 'permiso' => 'banco-preguntas.gestionar', 'titulo' => 'Bancos de preguntas', 'modulo' => 'Evaluaciones', 'rf' => ['RF16'], 'op' => 435],
    'bancos-de-preguntas.pregunta' => ['uri' => '/bancos-de-preguntas/{banco}/preguntas/crear', 'permiso' => 'banco-preguntas.gestionar', 'titulo' => 'Nueva pregunta', 'modulo' => 'Evaluaciones', 'rf' => ['RF16'], 'op' => 435, 'migas' => ['bancos-de-preguntas'], 'donde' => ['banco' => '[0-9]+'], 'maqueta' => '32 Banco de preguntas'],
    'bancos-de-preguntas.show' => ['uri' => '/bancos-de-preguntas/{banco}', 'permiso' => 'banco-preguntas.gestionar', 'titulo' => 'Banco de preguntas', 'modulo' => 'Evaluaciones', 'rf' => ['RF16'], 'op' => 435, 'migas' => ['bancos-de-preguntas'], 'maqueta' => '32 Banco de preguntas'],

    // Coordinación y jefatura
    'carreras' => ['uri' => '/carreras', 'permiso' => 'carreras.ver', 'titulo' => 'Carreras', 'modulo' => 'Academica', 'rf' => ['RF07', 'RF08'], 'op' => null],
    'carreras.show' => ['uri' => '/carreras/{carrera}', 'permiso' => 'carreras.ver', 'titulo' => 'Carrera', 'modulo' => 'Academica', 'rf' => ['RF07', 'RF08'], 'op' => null, 'migas' => ['carreras'], 'donde' => ['carrera' => '[0-9]+']],
    'carga-academica' => ['uri' => '/carga-academica', 'permiso' => 'secciones.gestionar|secciones.asignar-docente|secciones.cupo', 'titulo' => 'Carga académica', 'modulo' => 'Academica', 'rf' => ['RF08'], 'op' => 403, 'maqueta' => '23 Carga académica'],
    'matricula' => ['uri' => '/matricula', 'permiso' => 'matricula.gestionar', 'titulo' => 'Matrícula', 'modulo' => 'Academica', 'rf' => ['RF09'], 'op' => 407, 'maqueta' => '35 Matrícula'],
    'matricula.claves' => ['uri' => '/matricula/claves', 'permiso' => 'matricula.claves.gestionar', 'titulo' => 'Claves por asignatura', 'modulo' => 'Academica', 'rf' => ['RF29'], 'op' => 495, 'migas' => ['matricula'], 'maqueta' => '36 Claves por asignatura'],
    'matricula.solicitudes' => ['uri' => '/matricula/solicitudes', 'permiso' => 'matricula.gestionar', 'titulo' => 'Solicitudes de cambio de grupo', 'modulo' => 'Academica', 'rf' => ['RF09'], 'op' => 407, 'migas' => ['matricula'], 'maqueta' => '37 Solicitudes de cambio de grupo'],
    'matricula.plazos' => ['uri' => '/matricula/plazos', 'permiso' => 'matricula.plazo.gestionar|matricula.prorroga.otorgar', 'titulo' => 'Plazos de matrícula', 'modulo' => 'Academica', 'rf' => ['RF29'], 'op' => 495, 'migas' => ['matricula']],
    'usuarios.importar' => ['uri' => '/usuarios/importar', 'permiso' => 'usuarios.importar', 'titulo' => 'Importar estudiantes', 'modulo' => 'Identidad', 'rf' => ['RF04'], 'op' => 387],
    'usuarios.lote' => ['uri' => '/usuarios/lotes/{lote}', 'permiso' => 'usuarios.importar', 'titulo' => 'Lote de importación', 'modulo' => 'Identidad', 'rf' => ['RF04'], 'op' => 387, 'migas' => ['usuarios.importar']],
    'actas' => ['uri' => '/actas', 'permiso' => 'actas.validar', 'titulo' => 'Actas por validar', 'modulo' => 'Calificaciones', 'rf' => ['RF34'], 'op' => 515, 'maqueta' => '25 Actas por validar'],
    'clases-canceladas' => ['uri' => '/clases-canceladas', 'permiso' => 'clases.cancelar', 'titulo' => 'Clases canceladas', 'modulo' => 'Asistencia', 'rf' => ['RF32'], 'op' => 507, 'maqueta' => '43 Clases canceladas'],
    'cierre' => ['uri' => '/cierre', 'permiso' => 'cierre.autorizar', 'titulo' => 'Cierre del período', 'modulo' => 'Calificaciones', 'rf' => ['RF34'], 'op' => 515, 'maqueta' => '26 Cierre del período'],
    'desempeno-docente' => ['uri' => '/desempeno-docente', 'permiso' => 'desempeno-docente.ver', 'titulo' => 'Desempeño docente', 'modulo' => 'Reportes', 'rf' => ['RF33'], 'op' => 511, 'maqueta' => '27 Desempeño docente'],

    // Administración y soporte
    'usuarios' => ['uri' => '/usuarios', 'permiso' => 'usuarios.ver', 'titulo' => 'Usuarios', 'modulo' => 'Identidad', 'rf' => ['RF04'], 'op' => 387, 'maqueta' => '29 Usuarios'],
    'usuarios.crear' => ['uri' => '/usuarios/crear', 'permiso' => 'usuarios.gestionar', 'titulo' => 'Nuevo usuario', 'modulo' => 'Identidad', 'rf' => ['RF04'], 'op' => 387, 'migas' => ['usuarios'], 'maqueta' => '29 Usuarios'],
    'usuarios.roles' => ['uri' => '/usuarios/{usuario}/roles', 'permiso' => 'usuarios.ver', 'titulo' => 'Roles y asignaciones', 'modulo' => 'Identidad', 'rf' => ['RF04'], 'op' => 387, 'migas' => ['usuarios'], 'donde' => ['usuario' => '[0-9]+']],
    'usuarios.show' => ['uri' => '/usuarios/{usuario}', 'permiso' => 'usuarios.ver', 'titulo' => 'Usuario', 'modulo' => 'Identidad', 'rf' => ['RF04'], 'op' => 387, 'migas' => ['usuarios'], 'donde' => ['usuario' => '[0-9]+']],
    'personas.show' => ['uri' => '/personas/{usuario}', 'permiso' => 'perfiles.ver', 'titulo' => 'Perfil', 'modulo' => 'Identidad', 'rf' => ['RF36'], 'op' => null, 'donde' => ['usuario' => '[0-9]+']],
    'roles' => ['uri' => '/roles', 'permiso' => 'roles.gestionar', 'titulo' => 'Roles y permisos', 'modulo' => 'Identidad', 'rf' => ['RF03'], 'op' => 383],
    'periodos' => ['uri' => '/periodos', 'permiso' => 'periodos.gestionar', 'titulo' => 'Períodos', 'modulo' => 'Academica', 'rf' => ['RF06'], 'op' => 395],
    'periodos.calendario' => ['uri' => '/calendario-evaluaciones/{periodo?}', 'permiso' => 'periodos.calendario.gestionar', 'titulo' => 'Calendario de evaluaciones', 'modulo' => 'Academica', 'rf' => ['RF06'], 'op' => 393],
    'periodos.show' => ['uri' => '/periodos/{periodo}', 'permiso' => 'periodos.gestionar', 'titulo' => 'Período', 'modulo' => 'Academica', 'rf' => ['RF06', 'RF34'], 'op' => 395, 'migas' => ['periodos']],
    'catalogo' => ['uri' => '/catalogo', 'permiso' => 'asignaturas.gestionar', 'titulo' => 'Catálogo de asignaturas', 'modulo' => 'Academica', 'rf' => ['RF07'], 'op' => 399],
    'catalogo.estructura' => ['uri' => '/catalogo/estructura', 'permiso' => 'asignaturas.gestionar', 'titulo' => 'Departamentos, carreras, planes y aulas', 'modulo' => 'Academica', 'rf' => ['RF07'], 'op' => 399, 'migas' => ['catalogo']],
    'areas-asistencia' => ['uri' => '/areas-asistencia', 'permiso' => 'areas-asistencia.gestionar', 'titulo' => 'Áreas de asistencia', 'modulo' => 'Asistencia', 'rf' => ['RF26'], 'op' => 483, 'maqueta' => '34 Áreas de asistencia'],
    'integraciones' => ['uri' => '/integraciones', 'permiso' => 'integraciones.gestionar', 'titulo' => 'Integraciones', 'modulo' => 'Identidad', 'rf' => ['RNF09'], 'op' => null, 'maqueta' => '51 Integraciones'],
    'dispositivos' => ['uri' => '/dispositivos', 'permiso' => 'dispositivos.gestionar', 'titulo' => 'Solicitudes de dispositivo', 'modulo' => 'Identidad', 'rf' => ['RF27'], 'op' => 487, 'maqueta' => '30 Solicitudes de dispositivo'],
    'asistencia.diagnostico' => ['uri' => '/asistencia/diagnostico', 'permiso' => 'asistencia.diagnostico.ver', 'titulo' => 'Diagnóstico de asistencia', 'modulo' => 'Asistencia', 'rf' => ['RF20'], 'op' => 451, 'maqueta' => '31 Diagnóstico de asistencia'],

    // Consultas
    'reportes' => ['uri' => '/reportes', 'permiso' => 'reportes.ver', 'titulo' => 'Reportes', 'modulo' => 'Reportes', 'rf' => ['RF25'], 'op' => 475, 'maqueta' => '47 Reportes'],
    'oferta' => ['uri' => '/oferta', 'permiso' => 'oferta.estadisticas.ver', 'titulo' => 'Estadísticas de oferta', 'modulo' => 'Academica', 'rf' => ['RF10'], 'op' => 411, 'maqueta' => '50 Estadísticas de oferta'],
    'auditoria' => ['uri' => '/auditoria', 'permiso' => 'auditoria.ver', 'titulo' => 'Auditoría', 'modulo' => 'Auditoria', 'rf' => ['RF05'], 'op' => 391, 'maqueta' => '28 Auditoría departamental'],

];
