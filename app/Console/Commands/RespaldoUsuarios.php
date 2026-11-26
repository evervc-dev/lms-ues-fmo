<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DbContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Respalda y restaura los usuarios, sus roles (Spatie) y sus asignaciones institucionales
 * (perfiles docente/estudiante, jefaturas, coordinaciones y asignación a secciones).
 * Permite ejecutar seeders o reiniciar la base de datos y reorganizar a los usuarios exactamente igual.
 */
class RespaldoUsuarios extends Command
{
    protected $signature = 'usuarios:respaldo
                            {--restaurar : Restaura los usuarios y asignaciones desde el archivo de respaldo}
                            {--archivo= : Ruta personalizada para el archivo JSON de respaldo}';

    protected $description = 'Respalda o restaura usuarios, roles y asignaciones institucionales usando identificadores naturales';

    public function handle(): int
    {
        $archivo = $this->option('archivo') ?: storage_path('app/respaldos/usuarios_asignaciones.json');

        if ($this->option('restaurar')) {
            return $this->restaurar($archivo);
        }

        return $this->respaldar($archivo);
    }

    /**
     * Exporta usuarios, roles y asignaciones a un archivo JSON usando identificadores naturales.
     */
    private function respaldar(string $archivo): int
    {
        $this->info(__('Iniciando respaldo de usuarios, roles y asignaciones…'));

        $usuarios = User::with('roles')->get();
        $respaldo = [];

        foreach ($usuarios as $u) {
            $roles = $u->roles->pluck('name')->all();

            // Perfil de estudiante
            $perfilEstudiante = DB::table('student_profiles')->where('user_id', $u->id)->first();
            $carrerasEstudiante = DB::table('student_careers as sc')
                ->join('careers as c', 'c.id', '=', 'sc.career_id')
                ->join('study_plans as sp', 'sp.id', '=', 'sc.study_plan_id')
                ->where('sc.student_id', $u->id)
                ->get(['c.code as career_code', 'sp.name as plan_name', 'sc.current_cycle', 'sc.status'])
                ->map(fn ($c) => [
                    'career_code' => $c->career_code,
                    'plan_name' => $c->plan_name,
                    'current_cycle' => (int) $c->current_cycle,
                    'status' => $c->status,
                ])->all();

            // Perfil de docente
            $perfilDocente = DB::table('teacher_profiles as tp')
                ->join('departments as d', 'd.id', '=', 'tp.department_id')
                ->where('tp.user_id', $u->id)
                ->first(['d.code as department_code']);

            // Jefatura de departamento
            $jefaturas = DB::table('department_heads as dh')
                ->join('departments as d', 'd.id', '=', 'dh.department_id')
                ->where('dh.user_id', $u->id)
                ->get(['d.code as department_code', 'dh.valid_from', 'dh.valid_to'])
                ->map(fn ($j) => [
                    'department_code' => $j->department_code,
                    'valid_from' => $j->valid_from,
                    'valid_to' => $j->valid_to,
                ])->all();

            // Coordinación de carrera
            $coordinaciones = DB::table('career_coordinators as cc')
                ->join('careers as c', 'c.id', '=', 'cc.career_id')
                ->where('cc.user_id', $u->id)
                ->get(['c.code as career_code', 'cc.valid_from', 'cc.valid_to'])
                ->map(fn ($co) => [
                    'career_code' => $co->career_code,
                    'valid_from' => $co->valid_from,
                    'valid_to' => $co->valid_to,
                ])->all();

            // Asignaciones docentes a secciones
            $asignacionesDocentes = DB::table('teaching_assignments as ta')
                ->join('sections as s', 's.id', '=', 'ta.section_id')
                ->join('subjects as sub', 'sub.id', '=', 's.subject_id')
                ->join('academic_periods as p', 'p.id', '=', 's.period_id')
                ->where('ta.teacher_id', $u->id)
                ->get(['sub.code as subject_code', 's.code as section_code', 'p.name as period_name', 'ta.is_responsible', 'ta.started_at', 'ta.ended_at'])
                ->map(fn ($a) => [
                    'subject_code' => $a->subject_code,
                    'section_code' => $a->section_code,
                    'period_name' => $a->period_name,
                    'is_responsible' => (bool) $a->is_responsible,
                    'started_at' => $a->started_at,
                    'ended_at' => $a->ended_at,
                ])->all();

            $respaldo[] = [
                'email' => $u->institutional_email ?? $u->email,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'name' => $u->name,
                'is_active' => (bool) $u->is_active,
                'is_emergency' => (bool) $u->is_emergency,
                'avatar_url' => $u->avatar_url,
                'roles' => $roles,
                'student_profile' => $perfilEstudiante ? ['student_code' => $perfilEstudiante->student_code] : null,
                'student_careers' => $carrerasEstudiante,
                'teacher_profile' => $perfilDocente ? ['department_code' => $perfilDocente->department_code] : null,
                'department_heads' => $jefaturas,
                'career_coordinators' => $coordinaciones,
                'teaching_assignments' => $asignacionesDocentes,
            ];
        }

        File::ensureDirectoryExists(dirname($archivo));
        File::put($archivo, json_encode($respaldo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info(__('Respaldo guardado exitosamente en: :ruta', ['ruta' => $archivo]));
        $this->line(__('Total de usuarios respaldados: :count', ['count' => count($respaldo)]));

        return self::SUCCESS;
    }

    /**
     * Restaura usuarios, sincroniza roles y restablece perfiles y asignaciones usando identificadores naturales.
     */
    private function restaurar(string $archivo): int
    {
        if (! File::exists($archivo)) {
            $this->error(__('No se encontró el archivo de respaldo en: :ruta', ['ruta' => $archivo]));

            return self::FAILURE;
        }

        $contenido = File::get($archivo);
        $respaldo = json_decode($contenido, true);

        if (! is_array($respaldo)) {
            $this->error(__('El archivo de respaldo tiene un formato JSON inválido.'));

            return self::FAILURE;
        }

        $this->info(__('Iniciando restauración de usuarios y asignaciones desde: :ruta', ['ruta' => $archivo]));

        $restaurados = 0;

        DbContext::system(function () use ($respaldo, &$restaurados) {
            $departamentos = DB::table('departments')->pluck('id', 'code');
            $carreras = DB::table('careers')->pluck('id', 'code');
            $periodos = DB::table('academic_periods')->pluck('id', 'name');

            foreach ($respaldo as $item) {
                $email = $item['email'] ?? null;
                if (! $email) {
                    continue;
                }

                // 1. Crear o actualizar usuario
                $usuario = User::where('institutional_email', $email)->orWhere('email', $email)->first();

                if (! $usuario) {
                    $usuario = User::create([
                        'name' => $item['name'] ?? trim(($item['first_name'] ?? '').' '.($item['last_name'] ?? '')),
                        'first_name' => $item['first_name'] ?? '',
                        'last_name' => $item['last_name'] ?? '',
                        'institutional_email' => $email,
                        'email' => $email,
                        'is_active' => $item['is_active'] ?? true,
                        'is_emergency' => $item['is_emergency'] ?? false,
                        'avatar_url' => $item['avatar_url'] ?? null,
                    ]);
                } else {
                    $usuario->update([
                        'first_name' => $item['first_name'] ?? $usuario->first_name,
                        'last_name' => $item['last_name'] ?? $usuario->last_name,
                        'name' => $item['name'] ?? $usuario->name,
                        'is_active' => $item['is_active'] ?? $usuario->is_active,
                        'avatar_url' => $item['avatar_url'] ?? $usuario->avatar_url,
                    ]);
                }

                // 2. Sincronizar roles (Spatie)
                if (! empty($item['roles'])) {
                    $usuario->syncRoles($item['roles']);
                }

                // 3. Perfil de estudiante
                if (! empty($item['student_profile'])) {
                    DB::table('student_profiles')->updateOrInsert(
                        ['user_id' => $usuario->id],
                        [
                            'student_code' => $item['student_profile']['student_code'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                // 4. Carreras del estudiante
                if (! empty($item['student_careers'])) {
                    foreach ($item['student_careers'] as $sc) {
                        $carreraId = $carreras[$sc['career_code']] ?? null;
                        if (! $carreraId) {
                            continue;
                        }

                        $planId = DB::table('study_plans')->where('career_id', $carreraId)->where('name', $sc['plan_name'])->value('id');
                        if (! $planId) {
                            $planId = DB::table('study_plans')->where('career_id', $carreraId)->value('id');
                        }

                        if ($planId) {
                            DB::table('student_careers')->updateOrInsert(
                                [
                                    'student_id' => $usuario->id,
                                    'career_id' => $carreraId,
                                ],
                                [
                                    'study_plan_id' => $planId,
                                    'current_cycle' => $sc['current_cycle'] ?? 1,
                                    'status' => $sc['status'] ?? 'active',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    }
                }

                // 5. Perfil de docente
                if (! empty($item['teacher_profile'])) {
                    $deptId = $departamentos[$item['teacher_profile']['department_code']] ?? null;
                    if ($deptId) {
                        DB::table('teacher_profiles')->updateOrInsert(
                            ['user_id' => $usuario->id],
                            [
                                'department_id' => $deptId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }

                // 6. Jefatura de departamento
                if (! empty($item['department_heads'])) {
                    foreach ($item['department_heads'] as $jh) {
                        $deptId = $departamentos[$jh['department_code']] ?? null;
                        if ($deptId) {
                            DB::table('department_heads')->updateOrInsert(
                                [
                                    'user_id' => $usuario->id,
                                    'department_id' => $deptId,
                                ],
                                [
                                    'valid_from' => $jh['valid_from'],
                                    'valid_to' => $jh['valid_to'],
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    }
                }

                // 7. Coordinación de carrera
                if (! empty($item['career_coordinators'])) {
                    foreach ($item['career_coordinators'] as $cc) {
                        $carreraId = $carreras[$cc['career_code']] ?? null;
                        if ($carreraId) {
                            DB::table('career_coordinators')->updateOrInsert(
                                [
                                    'user_id' => $usuario->id,
                                    'career_id' => $carreraId,
                                ],
                                [
                                    'valid_from' => $cc['valid_from'],
                                    'valid_to' => $cc['valid_to'],
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    }
                }

                // 8. Asignaciones docentes a secciones
                if (! empty($item['teaching_assignments'])) {
                    foreach ($item['teaching_assignments'] as $ta) {
                        $periodoId = $periodos[$ta['period_name']] ?? null;
                        if (! $periodoId) {
                            continue;
                        }

                        $sectionId = DB::table('sections as s')
                            ->join('subjects as sub', 'sub.id', '=', 's.subject_id')
                            ->where('s.period_id', $periodoId)
                            ->where('sub.code', $ta['subject_code'])
                            ->where('s.code', $ta['section_code'])
                            ->value('s.id');

                        if ($sectionId) {
                            DB::table('teaching_assignments')->updateOrInsert(
                                [
                                    'section_id' => $sectionId,
                                    'teacher_id' => $usuario->id,
                                ],
                                [
                                    'is_responsible' => $ta['is_responsible'],
                                    'started_at' => $ta['started_at'] ?? now(),
                                    'ended_at' => $ta['ended_at'] ?? null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    }
                }

                $restaurados++;
            }
        });

        $this->info(__('Restauración completada con éxito. Se sincronizaron :count usuarios y sus asignaciones.', ['count' => $restaurados]));

        return self::SUCCESS;
    }
}
