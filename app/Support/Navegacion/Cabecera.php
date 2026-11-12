<?php

namespace App\Support\Navegacion;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Datos del Layout Aplicación: usuario, roles, menú, período activo, asistencia abierta y avisos sin leer
 * (docs/13 Layout/Layout base.md). El layout los pide al inicio; no se usa un view composer
 * porque Blade compila los componentes anónimos con namespace bajo un nombre con hash.
 */
class Cabecera
{
    public function __construct(private Menu $menu) {}

    /**
     * @return array{usuario: User, iniciales: string, roles: string, menu: array{grupos: array<int, array{titulo: ?string, opciones: array<int, Opcion>}>, conTitulos: bool}, periodoActivo: ?string, asistenciaAbierta: ?string, sinDispositivo: bool}
     */
    public function datos(): array
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return [
            'usuario' => $usuario,
            'iniciales' => $usuario->iniciales(),
            'roles' => $usuario->getRoleNames()->map(fn (string $rol) => __('roles.'.$rol))->join(', ', ' y '),
            'menu' => $this->menu->para($usuario),
            'periodoActivo' => DB::table('academic_periods')->where('status', 'active')->value('name'),
            'asistenciaAbierta' => $this->asistenciaAbierta($usuario),
            'sinDispositivo' => $this->sinDispositivo($usuario),
        ];
    }

    /**
     * Código de la materia con una sesión de asistencia abierta para el estudiante (aviso de la cabecera).
     */
    private function asistenciaAbierta(User $usuario): ?string
    {
        if (! $usuario->hasRole('estudiante')) {
            return null;
        }

        return DB::table('attendance_sessions')
            ->join('enrollments', 'enrollments.section_id', '=', 'attendance_sessions.section_id')
            ->join('sections', 'sections.id', '=', 'attendance_sessions.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('enrollments.student_id', $usuario->id)->where('enrollments.status', 'active')
            ->where('attendance_sessions.status', 'open')
            ->orderByDesc('attendance_sessions.opened_at')
            ->value('subjects.code');
    }

    /**
     * Indica si el estudiante no tiene un dispositivo móvil de confianza registrado y activo (RF27).
     */
    private function sinDispositivo(User $usuario): bool
    {
        if (! $usuario->hasRole('estudiante')) {
            return false;
        }

        return ! DB::table('student_devices')
            ->where('student_id', $usuario->id)
            ->where('status', 'active')
            ->exists();
    }
}
