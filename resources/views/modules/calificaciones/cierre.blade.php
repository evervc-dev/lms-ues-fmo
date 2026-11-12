{{-- Cierre del período (RF34, maqueta 26). --}}
@php($estados = ['draft' => ['gris', __('Sin publicar')], 'published' => ['azul', __('Por validar')], 'returned' => ['ambar', __('Devuelta')], 'validated' => ['verde', __('Validada')]])
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Cierre del período :p', ['p' => $periodo?->name ?? '—'])" :subtitulo="__('Autorice el cierre de su departamento cuando todas las actas estén validadas por los coordinadores.')">
        <x-slot:acciones>
            @if ($autorizado)
                <x-ui.badge color="verde">{{ __('Autorizado el :f', ['f' => \Illuminate\Support\Carbon::parse($autorizado->authorized_at)->format('d/m/Y')]) }}</x-ui.badge>
            @else
                <x-ui.button icono="lock-closed" :disabled="$total === 0 || $validadas < $total" wire:click="autorizar" wire:confirm="{{ __('Se autorizará el cierre del departamento. ¿Continuar?') }}">{{ __('Autorizar cierre') }}</x-ui.button>
            @endif
        </x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.card>
        <p class="mb-2 text-sm font-medium">{{ __(':v de :t actas validadas', ['v' => $validadas, 't' => $total]) }}</p>
        <x-ui.progress :valor="$total ? 100 * $validadas / $total : 0" :tono="$validadas === $total && $total > 0 ? 'exito' : 'marca'" />
    </x-ui.card>

    {{-- El botón queda deshabilitado hasta que estén todas las actas validadas: aquí se dice en texto qué
         falta, porque antes no había manera de saberlo (revisión del rol Jefe de departamento). --}}
    @if (! $autorizado && ($total === 0 || $validadas < $total))
        <x-ui.alert tipo="aviso" :titulo="__('Todavía no puede autorizar el cierre')">
            @if ($total === 0)
                <p>{{ __('Su departamento no tiene secciones en el período :p, así que no hay actas que validar ni cierre que autorizar.', ['p' => $periodo?->name ?? '—']) }}</p>
            @else
                <p>{{ trans_choice('{1} Falta 1 acta por validar de :t.|[2,*] Faltan :count actas por validar de :t.', $total - $validadas, ['t' => $total]) }}
                    {{ __('Hasta que el coordinador de la carrera las valide, el botón «Autorizar cierre» permanece deshabilitado. En la tabla de abajo se resaltan las secciones que faltan.') }}</p>
                <p class="mt-1">{{ $pendientes->count() > 12
                    ? __('Secciones pendientes: :s y :n más.', ['s' => $pendientes->take(12)->implode(', '), 'n' => $pendientes->count() - 12])
                    : __('Secciones pendientes: :s.', ['s' => $pendientes->implode(', ')]) }}</p>
            @endif
        </x-ui.alert>
    @endif

    @foreach ($carreras as $carrera => $secciones)
        <x-ui.card ajustar :titulo="__('Carrera :c', ['c' => $carrera])" :descripcion="__(':v de :t validadas', ['v' => $secciones->where('estado', 'validated')->count(), 't' => $secciones->count()])">
            <x-ui.table ajustar>
                <x-slot:cabecera><th>{{ __('Sección') }}</th><th>{{ __('Asignatura') }}</th><th>{{ __('Acta') }}</th></x-slot:cabecera>
                @foreach ($secciones->sortBy(fn ($s) => $s->estado === 'validated' ? 1 : 0)->values() as $s)
                    <tr @class(['bg-amber-50' => $s->estado !== 'validated'])><td class="font-semibold">{{ $s->code }} · {{ $s->grupo }}</td><td>{{ $s->name }}</td><td><x-ui.badge :color="$estados[$s->estado][0]">{{ $estados[$s->estado][1] }}</x-ui.badge></td></tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endforeach
</div>
