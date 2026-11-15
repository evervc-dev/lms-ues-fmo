<?php

namespace Database\Seeders\Escenarios;

use Illuminate\Support\Facades\DB;

/**
 * Un ciclo completo de la carrera (RF06, RF07, RF08, RF09, RF10, RF29).
 *
 * Lo que pidió Ever el 14/11/2026: «crear un ciclo para una carrera, con docentes, jefe de departamento,
 * coordinador de carrera y estudiantes», y «ver también todo lo de los horarios».
 *
 * Son cinco materias, cada una con su sección y su horario en una franja distinta, de modo que un mismo
 * estudiante pueda llevar las cinco sin choque de horario y un mismo docente pueda dar varias sin dar dos
 * clases a la misma hora. Los estudiantes del equipo quedan matriculados en las cinco; el relleno, solo
 * en la primera, que es la que se usa para listas largas.
 *
 * No crea un período nuevo a propósito: en PostgreSQL los períodos tienen restricción de traslape de
 * fechas, así que un escenario que inventara su propio ciclo chocaría con el activo.
 */
class CicloCompleto extends Escenario
{
    /** Materia => franjas [día, inicio, fin]. Cinco franjas que no se pisan entre sí. */
    private const MALLA = [
        'MAT115' => [[1, '07:00', '08:40'], [3, '07:00', '08:40']],
        'PRN115' => [[1, '08:50', '10:30'], [3, '08:50', '10:30']],
        'ISI115' => [[2, '07:00', '08:40'], [4, '07:00', '08:40']],
        'IDI115' => [[2, '08:50', '10:30'], [4, '08:50', '10:30']],
        'PSI115' => [[5, '07:00', '08:40'], [5, '08:50', '10:30']],
    ];

    public static function clave(): string
    {
        return 'ciclo-completo';
    }

    public static function titulo(): string
    {
        return 'Ciclo completo de una carrera';
    }

    public static function descripcion(): string
    {
        return 'Cinco materias con sección, horario sin choques, plantel docente, jefe, coordinador y estudiantes matriculados en todas.';
    }

    public static function roles(): array
    {
        return [
            'docente' => self::rol('Docentes: se reparten las cinco secciones', 1, 5),
            'jefe_departamento' => self::rol('Jefe del departamento: ve la carga académica'),
            'coordinador' => self::rol('Coordinador de la carrera: matricula y resuelve cambios de grupo'),
            'estudiante' => self::rol('Estudiantes del ciclo', 1, 10),
            'administrador' => self::rol('Administrador: catálogo y períodos', 0, 1),
        ];
    }

    public static function rfs(): array
    {
        return ['RF06', 'RF07', 'RF08', 'RF09', 'RF10', 'RF29'];
    }

    public static function rellenoSugerido(): int
    {
        return 25;
    }

    public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado
    {
        $resultado = new Resultado;
        $docentes = $reparto->varios('docente');
        $secciones = [];
        $claves = [];

        foreach (array_keys(self::MALLA) as $indice => $codigo) {
            $materia = $esqueleto->materia($codigo);
            $seccion = $esqueleto->seccion($materia, self::MALLA[$codigo]);
            $esqueleto->computos($seccion);
            $esqueleto->docente($docentes[$indice % count($docentes)], $seccion);

            foreach ($reparto->varios('estudiante') as $estudiante) {
                $esqueleto->matricular($estudiante, $seccion);
            }

            // El relleno solo en la primera materia: es la que se usa para probar listas y reportes largos.
            if ($indice === 0) {
                $esqueleto->relleno($reparto->relleno, $seccion);
            }

            $secciones[] = ['codigo' => $materia->code, 'nombre' => $materia->name, 'ulid' => $seccion->ulid, 'id' => $seccion->id];
            $claves[$materia->code] = $seccion->clave;

            $resultado->enlace('docente', 'secciones.contenidos', ['seccion' => $seccion->ulid], "{$materia->code} · {$materia->name}");
        }

        $esqueleto->jefe($reparto->uno('jefe_departamento'));
        $esqueleto->coordinador($reparto->uno('coordinador'));

        $resultado
            ->enlace('docente', 'secciones', [], 'Mis secciones')
            ->enlace('jefe_departamento', 'carga-academica', [], 'Carga académica del departamento y sus topes')
            ->enlace('jefe_departamento', 'secciones', [], 'Secciones del departamento')
            ->enlace('jefe_departamento', 'actas', [], 'Actas del departamento')
            ->enlace('coordinador', 'oferta', [], 'Oferta académica y sus estadísticas (RF10)')
            ->enlace('coordinador', 'matricula', [], 'Matrícula: inscribir y mover estudiantes')
            ->enlace('coordinador', 'matricula.claves', [], 'Claves de inscripción de las materias')
            ->enlace('coordinador', 'matricula.solicitudes', [], 'Solicitudes de cambio de grupo')
            ->enlace('coordinador', 'matricula.plazos', [], 'Plazos de inscripción')
            ->enlace('coordinador', 'carreras', [], 'Carreras y sus planes')
            ->enlace('estudiante', 'materias', [], 'Mis materias: deben salir las cinco')
            ->enlace('estudiante', 'inscripcion', [], 'Inscripción de materias (automatrícula con clave, RF29)')
            ->enlace('estudiante', 'mis-notas', [], 'Mis notas');

        if ($reparto->unoONulo('administrador') !== null) {
            $esqueleto->administrador($reparto->uno('administrador'));
            $resultado
                ->enlace('administrador', 'catalogo', [], 'Catálogo de asignaturas')
                ->enlace('administrador', 'catalogo.estructura', [], 'Estructura: departamentos, carreras y planes')
                ->enlace('administrador', 'periodos', [], 'Períodos académicos')
                ->enlace('administrador', 'oferta', [], 'Oferta académica');
        }

        $lista = implode(', ', array_map(fn (array $s) => $s['codigo'], $secciones));
        $periodo = DB::table('academic_periods')->where('id', $esqueleto->periodo()->id)->value('name');

        $resultado
            ->dato('secciones', $secciones)
            ->dato('claves_inscripcion', $claves)
            ->nota("Cinco secciones nuevas en el período {$periodo}: {$lista}.")
            ->nota('Los horarios no se pisan entre sí: lunes y miércoles (dos franjas), martes y jueves (dos) y viernes doble.')
            ->nota('Sin aula asignada, para no chocar con el horario del demo ni con el de otro escenario.')
            ->nota('Los estudiantes del equipo están en las cinco; el relleno solo en la primera.')
            ->nota('Las claves de inscripción de cada materia están más abajo, para probar la automatrícula.');

        return $resultado;
    }
}
