<?php

namespace App\Support\Navegacion;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Menú unificado (ADR-034): suma las opciones de todos los roles del usuario, agrupadas.
 * Ocultar una opción no autoriza nada: la ruta tiene su middleware `permission:` y el Service su alcance.
 */
class Menu
{
    public function __construct(private Request $request, private Contadores $contadores) {}

    /**
     * @return array{grupos: array<int, array{titulo: ?string, opciones: array<int, Opcion>}>, conTitulos: bool}
     */
    public function para(User $usuario): array
    {
        /** @var array<int, array{titulo: ?string, rol: ?string, opciones: array<int, array<string, mixed>>}> $definicion */
        $definicion = config('navegacion.grupos', []);
        $incluidas = [];
        $grupos = [];

        foreach ($definicion as $grupo) {
            if ($grupo['rol'] !== null && ! $usuario->hasRole($grupo['rol'])) {
                continue;
            }

            $opciones = [];
            foreach ($grupo['opciones'] as $opcion) {
                if (isset($incluidas[$opcion['clave']]) || ! $this->permitida($usuario, $opcion['permiso'] ?? null)) {
                    continue;
                }
                $incluidas[$opcion['clave']] = true;
                $opciones[] = $this->resolver($opcion, $usuario);
            }

            if ($opciones !== []) {
                $grupos[] = ['titulo' => $grupo['titulo'], 'opciones' => $opciones];
            }
        }

        $grupos = $this->soloLaMasPrecisa($grupos);
        $conTitulo = count(array_filter($grupos, fn (array $grupo) => $grupo['titulo'] !== null));

        return ['grupos' => $grupos, 'conTitulos' => $conTitulo >= 2];
    }

    /**
     * Una opción se marca activa también en sus subrutas (`usuarios` cubre `usuarios.importar`), de modo
     * que en «Importar estudiantes» quedaban resaltadas las dos. Gana la más precisa: la de nombre de
     * ruta más largo entre las que coinciden.
     *
     * @param  array<int, array{titulo: ?string, opciones: array<int, Opcion>}>  $grupos
     * @return array<int, array{titulo: ?string, opciones: array<int, Opcion>}>
     */
    private function soloLaMasPrecisa(array $grupos): array
    {
        $activas = collect($grupos)->pluck('opciones')->flatten()->filter(fn (Opcion $o) => $o->activa);
        if ($activas->count() < 2) {
            return $grupos;
        }

        $gana = $activas->sortByDesc(fn (Opcion $o) => mb_strlen($o->ruta))->first();

        return collect($grupos)->map(fn (array $grupo) => [
            'titulo' => $grupo['titulo'],
            'opciones' => array_map(fn (Opcion $o) => $o->activa && $o !== $gana ? $o->inactiva() : $o, $grupo['opciones']),
        ])->all();
    }

    private function permitida(User $usuario, ?string $permiso): bool
    {
        return $permiso === null || collect(explode('|', $permiso))->contains(fn (string $uno) => $usuario->checkPermissionTo($uno));
    }

    /**
     * @param  array<string, mixed>  $opcion
     */
    private function resolver(array $opcion, User $usuario): Opcion
    {
        $parametros = $opcion['parametros'] ?? [];
        $existe = Route::has($opcion['ruta']);

        return new Opcion(
            clave: $opcion['clave'],
            etiqueta: $opcion['etiqueta'],
            icono: $opcion['icono'],
            ruta: $opcion['ruta'],
            url: $existe ? route($opcion['ruta'], $parametros) : null,
            activa: $existe && $this->request->routeIs($opcion['ruta'], $opcion['ruta'].'.*')
                && collect($parametros)->every(fn ($valor, $clave) => $this->request->query($clave) === $valor),
            pendiente: $existe && Vistas::pendiente($opcion['ruta']),
            contador: $existe && isset($opcion['contador']) ? $this->contadores->para($opcion['contador'], $usuario) : 0,
            secundaria: (bool) ($opcion['secundaria'] ?? false),
            externa: (bool) ($opcion['externa'] ?? false),
        );
    }
}
