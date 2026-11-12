{{-- Claves por asignatura (RF29, ADR-050, maqueta 36). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Matrícula')" :subtitulo="__('Con la clave, el estudiante elige una sección con cupo y sin choque de horario. La clave se muestra una sola vez.')" />
    <x-ui.tabs :pestanas="$pestanas" />

    <x-ui.card :titulo="__('Aplicar a todas')" :descripcion="__('Pone la misma clave en todas las asignaturas de su ámbito, con el vencimiento que indique abajo. Las claves actuales dejan de funcionar.')">
        <form wire:submit="aplicarATodas" wire:confirm="{{ __('Todas las asignaturas de su ámbito tendrán esta clave y las actuales dejarán de funcionar. ¿Continuar?') }}" class="flex flex-wrap items-end gap-3">
            <x-ui.field class="min-w-64 flex-1" :etiqueta="__('Clave para todas las asignaturas')" para="claveComun" error="claveComun" :ayuda="__('De 6 a 40 letras, números o guiones.')" requerido>
                <x-ui.input id="claveComun" wire:model="claveComun" autocomplete="off" />
            </x-ui.field>
            <x-ui.button type="submit" variante="secundario">{{ __('Aplicar a todas') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card ajustar :titulo="__('Claves de las asignaturas de su ámbito')">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <x-ui.field class="min-w-64 flex-1" :etiqueta="__('Buscar asignatura')" para="buscarClave">
                <x-ui.search id="buscarClave" wire:model.live.debounce.300ms="buscar" :placeholder="__('Código o nombre de la asignatura…')" />
            </x-ui.field>
            <x-ui.field class="max-w-xs" :etiqueta="__('Clave (déjela vacía para generarla)')" para="claveManual" error="claveManual"><x-ui.input id="claveManual" wire:model="claveManual" autocomplete="off" /></x-ui.field>
            <x-ui.field class="max-w-xs" :etiqueta="__('Vencimiento para las claves nuevas (opcional)')" para="vence" error="vence"><x-ui.input id="vence" type="date" wire:model="vence" /></x-ui.field>
            @if ($buscar !== '')
                <x-ui.button tamano="sm" variante="fantasma" icono="x-mark" wire:click="limpiar">{{ __('Limpiar filtros') }}</x-ui.button>
            @endif
        </div>
        <x-ui.table ajustar :vacio="__('Ninguna asignatura de su ámbito coincide con esa búsqueda.')">
            <x-slot:cabecera><th>{{ __('Asignatura') }}</th><th class="text-right">{{ __('Secciones') }}</th><th>{{ __('Clave') }}</th><th class="text-right">{{ __('Inscritos con clave') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($claves as $c)
                <tr wire:key="clave-{{ $c->id }}">
                    <td><span class="font-semibold">{{ $c->code }}</span> · {{ $c->name }}</td>
                    <td class="text-right">{{ $c->secciones }}</td>
                    <td>@if ($c->clave_id)<x-ui.badge color="verde">{{ __('Activa') }}</x-ui.badge> <span class="text-xs text-texto-suave">{{ $c->valid_until ? __('vence :f', ['f' => \Illuminate\Support\Carbon::parse($c->valid_until)->format('d/m/Y')]) : __('sin vencimiento') }}</span>@else<x-ui.badge>{{ __('Sin clave') }}</x-ui.badge>@endif</td>
                    <td class="text-right">{{ $c->por_clave }}</td>
                    <td class="text-right whitespace-nowrap">
                        <x-ui.button tamano="sm" variante="fantasma" wire:click="generar({{ $c->id }})" :wire:confirm="$c->clave_id ? __('La clave actual dejará de funcionar. ¿Generar una nueva?') : null">{{ $c->clave_id ? __('Cambiar clave') : __('Generar clave') }}</x-ui.button>
                        @if ($c->clave_id)<x-ui.button tamano="sm" variante="peligro" wire:click="desactivar({{ $c->id }})" wire:confirm="{{ __('¿Desactivar la clave de :a? Los estudiantes ya no podrán matricularse con ella.', ['a' => $c->code]) }}">{{ __('Desactivar') }}</x-ui.button>@endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal nombre="clave-generada" :titulo="$aplicadas !== null ? __('Clave de todas las asignaturas') : __('Clave de :a', ['a' => $asignaturaNueva])">
        <div class="flex flex-col items-center gap-3 py-2 text-center">
            @if ($aplicadas !== null)
                <p class="text-sm font-medium text-texto">{{ trans_choice('{0} No hay asignaturas en su ámbito.|{1} Se aplicó a 1 asignatura.|[2,*] Se aplicó a :count asignaturas.', $aplicadas) }}</p>
            @endif
            <p class="text-sm text-texto-suave">{{ __('Compártala con sus estudiantes. Por seguridad no se podrá volver a ver: si se pierde, genere otra.') }}</p>
            <p class="rounded-xl bg-marca-50 px-6 py-3 font-mono text-2xl font-semibold tracking-wider text-marca-700 ring-1 ring-marca-200" x-data x-on:click="navigator.clipboard?.writeText($el.innerText)">{{ $claveNueva }}</p>
        </div>
        <x-slot:pie><x-ui.button x-on:click="$dispatch('cerrar-modal', 'clave-generada')">{{ __('Listo') }}</x-ui.button></x-slot:pie>
    </x-ui.modal>
</div>
