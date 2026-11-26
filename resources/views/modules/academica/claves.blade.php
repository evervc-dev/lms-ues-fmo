{{-- Claves por asignatura (RF29, ADR-050, maqueta 36). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Matrícula')" :subtitulo="__('Con la clave, el estudiante elige un grupo teórico con cupo y sin choque de horario. La clave se muestra una sola vez.')" />
    <x-ui.tabs :pestanas="$pestanas" />

    @if ($periodos->count() > 1)
        {{-- Las claves también se configuran en un período en preparación (Ciclo de vida del período académico). --}}
        <x-ui.field :etiqueta="__('Período')" para="periodoClaves" class="sm:w-80">
            <x-ui.select id="periodoClaves" wire:model.live="periodoId">
                @foreach ($periodos as $p)
                    <option value="{{ $p->id }}" @selected($p->id === $periodoActual)>{{ $p->name }} · {{ $p->status === 'active' ? __('en curso') : __('en preparación') }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
    @endif


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
            <x-slot:cabecera><th>{{ __('Asignatura') }}</th><th class="text-right">{{ __('Grupos') }}</th><th>{{ __('Clave') }}</th><th class="text-right">{{ __('Inscritos con clave') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($claves as $c)
                <tr wire:key="clave-{{ $c->id }}">
                    <td><span class="font-semibold">{{ $c->code }}</span> · {{ $c->name }}</td>
                    <td class="text-right">{{ $c->secciones }}</td>
                    <td>@if ($c->clave_id)<x-ui.badge color="verde">{{ __('Activa') }}</x-ui.badge> <span class="text-xs text-texto-suave">{{ $c->valid_until ? __('vence :f', ['f' => \Illuminate\Support\Carbon::parse($c->valid_until)->format('d/m/Y')]) : __('sin vencimiento') }}</span>@else<x-ui.badge>{{ __('Sin clave') }}</x-ui.badge>@endif</td>
                    <td class="text-right">{{ $c->por_clave }}</td>
                    <td class="text-right whitespace-nowrap">
                        <x-ui.button tamano="sm" variante="fantasma" wire:click="generar({{ $c->id }})" :wire:confirm="$c->clave_id ? __('La clave actual dejará de funcionar. ¿Generar una nueva?') : null">{{ $c->clave_id ? __('Cambiar clave') : __('Generar clave') }}</x-ui.button>
                        @if ($c->clave_id)
                            <x-ui.acciones-fila :etiqueta="__('Más acciones de :a', ['a' => $c->code])">
                                <x-ui.dropdown.item icono="x-circle" peligro x-on:click="abierto = false" wire:click="desactivar({{ $c->id }})" wire:confirm="{{ __('¿Desactivar la clave de :a? Los estudiantes ya no podrán matricularse con ella.', ['a' => $c->code]) }}">{{ __('Desactivar clave') }}</x-ui.dropdown.item>
                            </x-ui.acciones-fila>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    {{-- Al final y plegada: reemplaza todas las claves del ámbito (QA Jefe 21/11: era lo primero que se veía). --}}
    <details class="rounded-xl bg-white p-4 ring-1 ring-borde">
        <summary class="cursor-pointer text-sm font-semibold text-texto">{{ __('Acciones masivas') }}</summary>
        <form wire:submit="prepararAplicarATodas" class="mt-4 flex flex-col gap-3">
            <p class="text-sm text-texto-suave">{{ __('Pone la misma clave en todas las asignaturas de su ámbito, con el vencimiento indicado arriba. Las claves actuales dejan de funcionar.') }}</p>
            <div class="flex flex-wrap items-end gap-3">
                <x-ui.field class="min-w-64 flex-1" :etiqueta="__('Clave para todas las asignaturas')" para="claveComun" error="claveComun" :ayuda="__('De 6 a 40 letras, números o guiones.')" requerido>
                    <x-ui.input id="claveComun" wire:model="claveComun" autocomplete="off" />
                </x-ui.field>
                <x-ui.button type="submit" variante="peligro">{{ __('Aplicar a todas…') }}</x-ui.button>
            </div>
        </form>
    </details>

    @php($conClave = $ambito->whereNotNull('clave_id')->count())
    <x-ui.modal nombre="confirmar-clave-comun" :titulo="__('Cambiar la clave de todas las asignaturas')">
        <form wire:submit="aplicarATodas" id="form-clave-comun" class="flex flex-col gap-4">
            <x-ui.alert tipo="aviso">
                {{ trans_choice('{1} Afecta a 1 asignatura de su ámbito.|[0,*] Afecta a :count asignaturas de su ámbito.', $ambito->count()) }}
                {{ trans_choice('{0} Ninguna tiene clave activa.|{1} 1 tiene una clave activa que dejará de funcionar: sus estudiantes necesitarán la nueva.|[2,*] :count tienen una clave activa que dejará de funcionar: sus estudiantes necesitarán la nueva.', $conClave) }}
            </x-ui.alert>
            <x-ui.field :etiqueta="__('Escriba CAMBIAR para confirmar')" para="confirmacion" error="confirmacion" requerido>
                <x-ui.input id="confirmacion" wire:model="confirmacion" autocomplete="off" />
            </x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'confirmar-clave-comun')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-clave-comun" variante="peligro-solido">{{ __('Cambiar todas las claves') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    <x-ui.modal nombre="clave-generada" :titulo="$aplicadas !== null ? __('Clave de todas las asignaturas') : __('Clave de :a', ['a' => $asignaturaNueva])">
        <div class="flex flex-col items-center gap-3 py-2 text-center">
            @if ($aplicadas !== null)
                <p class="text-sm font-medium text-texto">{{ trans_choice('{0} No hay asignaturas en su ámbito.|{1} Se aplicó a 1 asignatura.|[2,*] Se aplicó a :count asignaturas.', $aplicadas) }}</p>
            @endif
            <p class="text-sm text-texto-suave">{{ __('Compártala con sus estudiantes. Por seguridad no se podrá volver a ver: si se pierde, genere otra.') }}</p>
            <p class="rounded-xl bg-marca-50 px-6 py-3 font-mono text-2xl font-semibold tracking-wider text-marca-700 ring-1 ring-marca-200">{{ $claveNueva }}</p>
            {{-- Copiar la clave o un mensaje listo para los estudiantes (QA Jefe 21/11: había que seleccionarla a mano). --}}
            <div class="flex flex-wrap justify-center gap-2" x-data="{ copiado: '' }">
                <x-ui.button tamano="sm" variante="secundario" x-on:click="navigator.clipboard?.writeText(@js($claveNueva)); copiado = 'clave'">
                    <span x-text="copiado === 'clave' ? @js(__('Clave copiada')) : @js(__('Copiar clave'))"></span>
                </x-ui.button>
                <x-ui.button tamano="sm" variante="secundario" x-on:click="navigator.clipboard?.writeText(@js(__('Clave de matrícula de :a: :c. Inscríbase en «Inscripción de materias» del LMS UES FMO.', ['a' => $aplicadas !== null ? __('todas las asignaturas') : $asignaturaNueva, 'c' => $claveNueva]))); copiado = 'mensaje'">
                    <span x-text="copiado === 'mensaje' ? @js(__('Mensaje copiado')) : @js(__('Copiar mensaje para estudiantes'))"></span>
                </x-ui.button>
            </div>
        </div>
        <x-slot:pie><x-ui.button x-on:click="$dispatch('cerrar-modal', 'clave-generada')">{{ __('Listo') }}</x-ui.button></x-slot:pie>
    </x-ui.modal>
</div>
