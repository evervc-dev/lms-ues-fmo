<?php

namespace App\Modules\Reportes\Services;

use App\Models\User;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Reportes\Notifications\ReporteProgramado;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reportes (RF25): asistencia, notas, bajo rendimiento y carga docente del período elegido, siempre dentro
 * del alcance del rol (el docente, solo sus secciones), con la carrera de cada fila y un gráfico. Exportación a
 * Excel, PDF y CSV, y programación por correo, que hace el job de la cola «reportes» con el período en curso.
 */
class ReporteService
{
    public const TIPOS = [
        'asistencia' => 'Asistencia por sección',
        'notas' => 'Notas por sección',
        'bajo_rendimiento' => 'Estudiantes en riesgo',
        'carga_docente' => 'Carga docente',
    ];

    /** @var array<string, array<int|string, string>> Carreras ya consultadas en este reporte. */
    private array $memoria = [];

    public function __construct(private Alcance $alcance) {}

    /**
     * Un reporte del período elegido (por defecto, el en curso) dentro del alcance del rol, con la carrera de cada
     * fila: una asignatura puede estar en el plan de varias carreras (informe de QA del rol Jefe, 31/10/2026).
     *
     * @param  int|null  $periodo  Uno de `periodos()`; otro valor, o ninguno, es el período en curso (R-12).
     * @param  int|null  $carrera  Una de las carreras de su alcance; otra se ignora. En «Estudiantes en riesgo» filtra por la
     *                             carrera del estudiante y en los demás por la de la asignatura.
     * @return array{columnas: array<int, string>, filas: Collection<int, array<int, mixed>>, periodo: ?\stdClass, carrera: ?int}
     */
    public function generar(User $usuario, string $tipo, ?int $periodo = null, ?int $carrera = null): array
    {
        $this->alcance->exigir($usuario, 'reportes.ver');
        abort_unless(array_key_exists($tipo, self::TIPOS), 404);
        $periodos = $this->periodos();
        $elegido = $periodos->firstWhere('id', $periodo) ?? $periodos->firstWhere('status', 'active');
        $carrera = $this->alcance->carrerasVisibles($usuario, 'reportes.ver')->contains('id', $carrera) ? $carrera : null;

        $secciones = $this->alcance->secciones(DB::table('sections')->where('period_id', $elegido?->id), $usuario, 'reportes.ver')
            ->when($carrera !== null && $tipo !== 'bajo_rendimiento', fn ($q) => $q->whereIn('sections.subject_id', $this->alcance->asignaturasDeCarreras([(int) $carrera])))
            ->pluck('sections.id');
        $base = fn () => DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')->whereIn('sections.id', $secciones)
            ->orderBy('subjects.code')->orderBy('sections.code');
        $carreraDe = fn (Collection $filas) => $this->carrerasDeAsignaturas($filas->pluck('subject_id')->all());

        /** @var array{columnas: array<int, string>, filas: Collection<int, array<int, mixed>>} $reporte */
        $reporte = match ($tipo) {
            'asistencia' => [
                'columnas' => [__('Sección'), __('Asignatura'), __('Carrera'), __('Estudiantes'), __('Clases'), __('Asistencia %')],
                'filas' => ($filas = $base()->get(['sections.id', 'sections.subject_id', 'subjects.code', 'subjects.name', 'sections.code as grupo',
                    DB::raw("(select count(*) from enrollments e where e.section_id = sections.id and e.status = 'active') as estudiantes"),
                    DB::raw('(select count(*) from attendance_sessions s where s.section_id = sections.id) as clases'),
                    DB::raw('(select '.AsistenciaService::porcentajeSql().' from attendance_records r where r.section_id = sections.id) as porcentaje')]))
                    ->map(fn ($s) => [$s->code.' · '.$s->grupo, $s->name, $carreraDe($filas)[$s->subject_id] ?? '—', $s->estudiantes, $s->clases, $s->porcentaje]),
            ],
            'notas' => [
                'columnas' => [__('Sección'), __('Asignatura'), __('Carrera'), __('Notas publicadas'), __('Promedio'), __('Notas < 6')],
                'filas' => ($filas = $base()->get(['sections.id', 'sections.subject_id', 'subjects.code', 'subjects.name', 'sections.code as grupo',
                    DB::raw("(select count(*) from grades g where g.section_id = sections.id and g.status = 'published') as publicadas"),
                    DB::raw("(select round(avg(g.score), 2) from grades g where g.section_id = sections.id and g.status = 'published') as promedio"),
                    DB::raw("(select count(*) from grades g where g.section_id = sections.id and g.status = 'published' and g.score < 6) as bajas")]))
                    ->map(fn ($s) => [$s->code.' · '.$s->grupo, $s->name, $carreraDe($filas)[$s->subject_id] ?? '—', $s->publicadas, $s->promedio, $s->bajas]),
            ],
            'bajo_rendimiento' => [
                'columnas' => [__('Estudiante'), __('Carné'), __('Carrera'), __('Sección'), __('Alerta'), __('Desde')],
                'filas' => ($filas = DB::table('academic_alerts as a')->join('enrollments as e', 'e.id', '=', 'a.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
                    ->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')->join('sections', 'sections.id', '=', 'e.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                    ->whereIn('e.section_id', $secciones)->whereNull('a.closed_at')
                    ->when($carrera !== null, fn ($q) => $q->whereExists(fn ($c) => $c->from('student_careers as sc')->whereColumn('sc.student_id', 'u.id')
                        ->where('sc.career_id', $carrera)->where('sc.status', 'active')))
                    ->orderBy('u.last_name')->limit(2000)
                    ->get(['u.id as estudiante', 'u.first_name', 'u.last_name', 'p.student_code', 'subjects.code', 'sections.code as grupo', 'a.alert_type', 'a.opened_at']))
                    ->map(fn ($a) => [$a->last_name.', '.$a->first_name, $a->student_code, $this->carrerasDeEstudiantes($filas->pluck('estudiante')->all())[$a->estudiante] ?? '—',
                        $a->code.' · '.$a->grupo, __(self::ALERTAS[$a->alert_type] ?? $a->alert_type), Carbon::parse($a->opened_at)->format('d/m/Y')]),
            ],
            'carga_docente' => [
                'columnas' => [__('Docente'), __('Secciones'), __('Estudiantes')],
                'filas' => DB::table('teaching_assignments as t')->join('users as u', 'u.id', '=', 't.teacher_id')->whereIn('t.section_id', $secciones)->whereNull('t.ended_at')
                    ->groupBy('u.id', 'u.first_name', 'u.last_name')->orderBy('u.last_name')
                    ->get(['u.first_name', 'u.last_name', DB::raw('count(*) as secciones'),
                        DB::raw("sum((select count(*) from enrollments e where e.section_id = t.section_id and e.status = 'active')) as estudiantes")])
                    ->map(fn ($d) => [$d->last_name.', '.$d->first_name, $d->secciones, (int) $d->estudiantes]),
            ],
        };

        return $reporte + ['periodo' => $elegido, 'carrera' => $carrera];
    }

    /**
     * Períodos que se pueden consultar: el en curso y los cerrados, del más reciente al más antiguo.
     *
     * @return Collection<int, \stdClass>
     */
    public function periodos(): Collection
    {
        return DB::table('academic_periods')->whereIn('status', ['active', 'closed'])->orderByDesc('start_date')->get(['id', 'name', 'status']);
    }

    /**
     * Lo que dibuja el gráfico de cada reporte (R-11): una sola serie, la medida principal de cada fila; en
     * «Estudiantes en riesgo», cuántas alertas abiertas hay de cada tipo. Las filas sin dato no se dibujan.
     *
     * @param  Collection<int, array<int, mixed>>  $filas
     * @return array{titulo: string, etiquetas: array<int, string>, valores: array<int, float>, maximo: ?int, sufijo: string, enteros: bool, recortado: bool}
     */
    public static function grafico(string $tipo, Collection $filas): array
    {
        [$titulo, $etiqueta, $valor, $maximo, $sufijo] = match ($tipo) {
            'asistencia' => [__('Asistencia por sección'), 0, 5, 100, ' %'],
            'notas' => [__('Promedio de las notas publicadas por sección'), 0, 4, 10, ''],
            'carga_docente' => [__('Estudiantes a cargo de cada docente'), 0, 2, null, ''],
            default => [__('Alertas abiertas por tipo'), null, null, null, ''],
        };

        $puntos = $etiqueta === null
            ? $filas->countBy(fn (array $f) => (string) $f[4])->map(fn (int $n, string $tipoAlerta) => [$tipoAlerta, $n])->values()
            : $filas->filter(fn (array $f) => $f[$valor] !== null)->map(fn (array $f) => [(string) $f[$etiqueta], (float) $f[$valor]])->values();

        return [
            'titulo' => $titulo,
            'etiquetas' => $puntos->take(self::BARRAS)->pluck(0)->all(),
            'valores' => $puntos->take(self::BARRAS)->pluck(1)->map(fn ($v) => (float) $v)->all(),
            'maximo' => $maximo,
            'sufijo' => $sufijo,
            // Conteos (estudiantes, alertas): el eje va de uno en uno, sin decimales.
            'enteros' => in_array($tipo, ['carga_docente', 'bajo_rendimiento'], true),
            'recortado' => $puntos->count() > self::BARRAS,
        ];
    }

    /** Barras que dibuja como mucho un gráfico; la tabla y la exportación traen todas las filas. */
    private const BARRAS = 40;

    private const ALERTAS = [
        'attendance_85' => 'Asistencia < 85 %',
        'attendance_78' => 'Asistencia < 78 %',
        'attendance_75' => 'Asistencia < 75 %',
        'grade_risk' => 'Riesgo de reprobar',
    ];

    /**
     * Códigos de las carreras en cuyo plan está cada asignatura, separados por coma: «ISI, LMA».
     *
     * @param  array<int, mixed>  $asignaturas
     * @return array<int|string, string>
     */
    private function carrerasDeAsignaturas(array $asignaturas): array
    {
        return $this->memoria['asig'.implode(',', $asignaturas)] ??= DB::table('career_subjects as cs')->join('study_plans as sp', 'sp.id', '=', 'cs.study_plan_id')
            ->join('careers as c', 'c.id', '=', 'sp.career_id')->whereIn('cs.subject_id', $asignaturas)->orderBy('c.code')
            ->get(['cs.subject_id', 'c.code'])->groupBy('subject_id')->map(fn (Collection $g) => $g->pluck('code')->unique()->implode(', '))->all();
    }

    /**
     * Códigos de las carreras activas de cada estudiante: «ISI».
     *
     * @param  array<int, mixed>  $estudiantes
     * @return array<int|string, string>
     */
    private function carrerasDeEstudiantes(array $estudiantes): array
    {
        return $this->memoria['est'.implode(',', $estudiantes)] ??= DB::table('student_careers as sc')->join('careers as c', 'c.id', '=', 'sc.career_id')
            ->whereIn('sc.student_id', $estudiantes)->where('sc.status', 'active')->orderBy('c.code')
            ->get(['sc.student_id', 'c.code'])->groupBy('student_id')->map(fn (Collection $g) => $g->pluck('code')->unique()->implode(', '))->all();
    }

    /**
     * @return Collection<int, object>
     */
    public function programados(User $usuario): Collection
    {
        return DB::table('scheduled_reports')->where('user_id', $usuario->id)->orderBy('next_run_at')->get();
    }

    /**
     * Programa el envío por correo. Cada usuario tiene un solo envío de cada reporte con cada frecuencia
     * (índice único `scheduled_reports_user_id_report_type_frequency_unique`): si ya lo tiene, se le avisa
     * en vez de repetirlo, porque antes se podía programar el mismo reporte tantas veces como clics
     * (revisión de QA del 27/10/2026).
     */
    public function programar(User $usuario, string $tipo, string $frecuencia): void
    {
        $this->alcance->exigir($usuario, 'reportes.programar');
        if (! array_key_exists($tipo, self::TIPOS) || ! in_array($frecuencia, ['weekly', 'monthly'], true)) {
            throw ValidationException::withMessages(['tipo' => __('Elija un reporte y una frecuencia válidos.')]);
        }
        $siguiente = $frecuencia === 'weekly' ? now()->next('Monday')->setTime(7, 0) : now()->addMonth()->startOfMonth()->setTime(7, 0);
        $repetido = ValidationException::withMessages(['tipo' => __('Ya tiene programado «:reporte» con esa frecuencia. Cancele el envío de la lista si quiere cambiarlo.', ['reporte' => __(self::TIPOS[$tipo])])]);

        DbContext::run(function () use ($usuario, $tipo, $frecuencia, $siguiente, $repetido) {
            $yaLoTiene = DB::table('scheduled_reports')->where('user_id', $usuario->id)->where('report_type', $tipo)
                ->where('frequency', $frecuencia)->lockForUpdate()->exists();
            if ($yaLoTiene) {
                throw $repetido;
            }

            try {
                DB::table('scheduled_reports')->insert(['user_id' => $usuario->id, 'report_type' => $tipo, 'filters' => json_encode([]), 'frequency' => $frecuencia,
                    'next_run_at' => $siguiente, 'created_at' => now(), 'updated_at' => now()]);
            } catch (UniqueConstraintViolationException) {
                // Dos clics a la vez: el índice único es la última palabra.
                throw $repetido;
            }
        }, 'report.scheduled');
    }

    /**
     * Contenido CSV del reporte, con BOM para que Excel abra bien los acentos.
     *
     * @param  array{columnas: array<int, string>, filas: Collection<int, array<int, mixed>>}  $reporte
     */
    public static function csv(array $reporte): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, $reporte['columnas']);
        foreach ($reporte['filas'] as $fila) {
            fputcsv($salida, $fila);
        }
        rewind($salida);
        $contenido = (string) stream_get_contents($salida);
        fclose($salida);

        return $contenido;
    }

    /**
     * Genera los reportes programados que ya tocan y se los manda por correo a quien los programó (RF25). Lo corre
     * el job EnviarReportesProgramados en la cola «reportes».
     *
     * Idempotente: cada reporte se toma con la fila bloqueada y su próxima fecha avanza en la misma transacción,
     * así que repetir el job no lo envía dos veces. Si quien lo programó ya no tiene permiso, no se envía.
     *
     * @return int Reportes enviados.
     */
    public function enviarProgramados(): int
    {
        $enviados = 0;
        foreach (DB::table('scheduled_reports')->where('next_run_at', '<=', now())->pluck('id') as $id) {
            $envio = DbContext::system(function () use ($id) {
                $programado = DB::table('scheduled_reports')->where('id', $id)->where('next_run_at', '<=', now())->lockForUpdate()->first();
                if ($programado === null) {
                    return null;
                }
                DB::table('scheduled_reports')->where('id', $id)->update(['next_run_at' => self::siguiente($programado->frequency, Carbon::parse($programado->next_run_at)), 'updated_at' => now()]);

                return $programado;
            }, 'report.scheduled_run');
            $usuario = $envio ? User::query()->where('is_active', true)->find($envio->user_id) : null;
            if ($usuario === null || ! array_key_exists($envio->report_type, self::TIPOS)) {
                continue;
            }

            try {
                $reporte = $this->generar($usuario, $envio->report_type);
            } catch (AuthorizationException) {
                continue;
            }
            $usuario->notify(new ReporteProgramado($envio->report_type, self::csv($reporte), count($reporte['filas'])));
            $enviados++;
        }

        return $enviados;
    }

    /**
     * Próxima fecha de un reporte programado: el lunes siguiente o el primer día del mes siguiente, a las 7:00, y
     * siempre en el futuro aunque el programador haya estado detenido.
     */
    private static function siguiente(string $frecuencia, Carbon $desde): Carbon
    {
        $proxima = $desde->copy();
        do {
            $proxima = $frecuencia === 'weekly' ? $proxima->next('Monday')->setTime(7, 0) : $proxima->addMonthNoOverflow()->startOfMonth()->setTime(7, 0);
        } while ($proxima->lte(now()));

        return $proxima;
    }

    public function cancelarProgramado(User $usuario, int $id): void
    {
        DbContext::run(fn () => DB::table('scheduled_reports')->where('id', $id)->where('user_id', $usuario->id)->delete(), 'report.unscheduled');
    }
}
