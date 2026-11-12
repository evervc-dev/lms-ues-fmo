<?php

namespace App\Modules\Asistencia\Services;

/**
 * Geometría de las áreas de asistencia por polígono (RF26). Solo cálculo, sin base de datos ni permisos.
 *
 * Los vértices llegan como [[lat, lng], …], sin repetir el primero al final. Para medir se proyectan a un plano
 * local en metros (equirrectangular, centrado en el polígono): a la escala de un campus el error es de
 * centímetros, mucho menor que la precisión del GPS de un teléfono.
 */
class GeocercaService
{
    private const RADIO_TIERRA = 6_371_000;

    /** Un punto a menos de esta distancia del borde cuenta como dentro. */
    private const TOLERANCIA_BORDE = 0.01;

    /**
     * Distancia en metros del punto al polígono: 0 si está dentro o en el borde; si no, al lado más cercano.
     *
     * @param  array<int, array{0: float, 1: float}>  $vertices
     */
    public function distancia(float $lat, float $lng, array $vertices): float
    {
        $origen = $this->origen($vertices);
        $plano = array_map(fn (array $v) => $this->proyectar($v[0], $v[1], $origen), $vertices);
        $punto = $this->proyectar($lat, $lng, $origen);

        $alBorde = INF;
        foreach ($this->lados($plano) as [$a, $b]) {
            $alBorde = min($alBorde, $this->distanciaASegmento($punto, $a, $b));
        }

        return $alBorde <= self::TOLERANCIA_BORDE || $this->contiene($punto, $plano) ? 0.0 : $alBorde;
    }

    /**
     * Si dos lados no consecutivos se cruzan (polígono «en ocho»): no tiene un dentro bien definido.
     *
     * @param  array<int, array{0: float, 1: float}>  $vertices
     */
    public function seCruza(array $vertices): bool
    {
        $origen = $this->origen($vertices);
        $lados = $this->lados(array_map(fn (array $v) => $this->proyectar($v[0], $v[1], $origen), $vertices));
        $total = count($lados);

        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                $consecutivos = $j === $i + 1 || ($i === 0 && $j === $total - 1);
                if (! $consecutivos && $this->segmentosSeCortan($lados[$i][0], $lados[$i][1], $lados[$j][0], $lados[$j][1])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Centroide del polígono (lat, lng), para mostrarlo en la lista y centrar el mapa.
     *
     * @param  array<int, array{0: float, 1: float}>  $vertices
     * @return array{0: float, 1: float}
     */
    public function centroide(array $vertices): array
    {
        $origen = $this->origen($vertices);
        $area = $cx = $cy = 0.0;
        foreach ($this->lados(array_map(fn (array $v) => $this->proyectar($v[0], $v[1], $origen), $vertices)) as [[$x1, $y1], [$x2, $y2]]) {
            $cruz = $x1 * $y2 - $x2 * $y1;
            $area += $cruz;
            $cx += ($x1 + $x2) * $cruz;
            $cy += ($y1 + $y2) * $cruz;
        }

        if (abs($area) < 1e-9) {
            return $origen;
        }

        return $this->desproyectar($cx / (3 * $area), $cy / (3 * $area), $origen);
    }

    /**
     * Punto de referencia de la proyección: el promedio de los vértices.
     *
     * @param  array<int, array{0: float, 1: float}>  $vertices
     * @return array{0: float, 1: float}
     */
    private function origen(array $vertices): array
    {
        return [array_sum(array_column($vertices, 0)) / count($vertices), array_sum(array_column($vertices, 1)) / count($vertices)];
    }

    /**
     * @param  array{0: float, 1: float}  $origen
     * @return array{0: float, 1: float} x (este) e y (norte) en metros
     */
    private function proyectar(float $lat, float $lng, array $origen): array
    {
        return [
            deg2rad($lng - $origen[1]) * cos(deg2rad($origen[0])) * self::RADIO_TIERRA,
            deg2rad($lat - $origen[0]) * self::RADIO_TIERRA,
        ];
    }

    /**
     * @param  array{0: float, 1: float}  $origen
     * @return array{0: float, 1: float}
     */
    private function desproyectar(float $x, float $y, array $origen): array
    {
        return [
            $origen[0] + rad2deg($y / self::RADIO_TIERRA),
            $origen[1] + rad2deg($x / (self::RADIO_TIERRA * cos(deg2rad($origen[0])))),
        ];
    }

    /**
     * Lados del polígono cerrado: cada vértice con el siguiente, y el último con el primero.
     *
     * @param  array<int, array{0: float, 1: float}>  $puntos
     * @return array<int, array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}>
     */
    private function lados(array $puntos): array
    {
        $lados = [];
        foreach ($puntos as $i => $punto) {
            $lados[] = [$punto, $puntos[($i + 1) % count($puntos)]];
        }

        return $lados;
    }

    /**
     * Par-impar (ray casting): cuántas veces una semirrecta hacia el este cruza el borde.
     *
     * @param  array{0: float, 1: float}  $p
     * @param  array<int, array{0: float, 1: float}>  $plano
     */
    private function contiene(array $p, array $plano): bool
    {
        $dentro = false;
        foreach ($this->lados($plano) as [[$x1, $y1], [$x2, $y2]]) {
            if (($y1 > $p[1]) !== ($y2 > $p[1]) && $p[0] < ($x2 - $x1) * ($p[1] - $y1) / ($y2 - $y1) + $x1) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /**
     * @param  array{0: float, 1: float}  $p
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function distanciaASegmento(array $p, array $a, array $b): float
    {
        [$dx, $dy] = [$b[0] - $a[0], $b[1] - $a[1]];
        $largo = $dx * $dx + $dy * $dy;
        $t = $largo === 0.0 ? 0.0 : max(0.0, min(1.0, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $largo));

        return hypot($p[0] - ($a[0] + $t * $dx), $p[1] - ($a[1] + $t * $dy));
    }

    /**
     * Si los segmentos ab y cd se tocan (incluido un extremo sobre el otro segmento).
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @param  array{0: float, 1: float}  $c
     * @param  array{0: float, 1: float}  $d
     */
    private function segmentosSeCortan(array $a, array $b, array $c, array $d): bool
    {
        $orientacion = fn (array $p, array $q, array $r) => ($q[0] - $p[0]) * ($r[1] - $p[1]) - ($q[1] - $p[1]) * ($r[0] - $p[0]);
        $sobre = fn (array $p, array $q, array $r) => min($p[0], $q[0]) <= $r[0] && $r[0] <= max($p[0], $q[0]) && min($p[1], $q[1]) <= $r[1] && $r[1] <= max($p[1], $q[1]);

        [$o1, $o2, $o3, $o4] = [$orientacion($a, $b, $c), $orientacion($a, $b, $d), $orientacion($c, $d, $a), $orientacion($c, $d, $b)];
        if ((($o1 > 0 && $o2 < 0) || ($o1 < 0 && $o2 > 0)) && (($o3 > 0 && $o4 < 0) || ($o3 < 0 && $o4 > 0))) {
            return true;
        }

        return ($o1 == 0 && $sobre($a, $b, $c)) || ($o2 == 0 && $sobre($a, $b, $d))
            || ($o3 == 0 && $sobre($c, $d, $a)) || ($o4 == 0 && $sobre($c, $d, $b));
    }
}
