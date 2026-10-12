<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audiencia de cada aviso (RF23)
    |--------------------------------------------------------------------------
    |
    | Roles que pueden recibir cada tipo de aviso, tomados de quién lo envía en
    | App\Modules\Comunicacion\Listeners\EnviarAvisos y en los Services. Solo
    | sirve para no ofrecerle a nadie preferencias de avisos que nunca va a
    | recibir: no autoriza ni filtra el envío.
    |
    | Un aviso que no aparezca aquí se le ofrece a todos, para que un evento
    | nuevo no desaparezca de «Mi perfil → Notificaciones» por olvido.
    |
    */

    'audiencias' => [
        'assessment.created' => ['estudiante'],
        'resource.published' => ['estudiante'],
        'video_session.upcoming' => ['estudiante'],
        'schedule.changed' => ['estudiante', 'docente'],
        'grade.published' => ['estudiante'],
        'feedback.available' => ['estudiante'],
        'deadline.upcoming' => ['estudiante'],
        'enrollment.changed' => ['estudiante'],
        'academic_alert.opened' => ['estudiante'],
        'grade_sheet.returned' => ['docente'],
        'grade_sheet.published' => ['coordinador'],
        // El estudiante recibe la resolución de lo suyo; quienes atienden la bandeja, la llegada de una
        // solicitud y el cierre que haga otra persona del equipo (RF27).
        'device_request.pending' => ['soporte_tecnico'],
        'device_request.resolved' => ['estudiante', 'soporte_tecnico'],
        'class.cancelled' => ['estudiante', 'docente'],
        'forum.reply' => ['estudiante', 'docente'],
        'mass_message.sent' => ['estudiante'],
        'grade_review.requested' => ['docente'],
        'grade_review.resolved' => ['estudiante'],
        'extension.granted' => ['estudiante'],
        'period.reopened' => ['coordinador'],
    ],

];
