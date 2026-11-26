{{--
    Datos comunes de una tarea o un cuestionario (RF15, RF16): título, instrucciones, cómputo, peso y fechas.
    Variables: $computos, $sufijo ('' en la tarea, 'C' en el cuestionario: los ids que usan las pruebas de navegador),
    $titulo (encabezado de la tarjeta). Calcula al momento cuánto vale en la nota final y cuántos días queda abierta.
--}}
@php
    $pesos = $computos->mapWithKeys(fn ($c) => [(string) $c->id => (float) $c->weight_percent])->all();
    $porcentaje = fn ($valor) => rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');
@endphp
<x-ui.card :titulo="$titulo" :descripcion="__('Qué es, cuánto vale y cuándo se puede hacer.')">
    <div class="flex flex-col gap-4"
        x-data="{
            pesos: @js($pesos), computo: String($wire.componente ?? ''), peso: Number($wire.peso), abre: $wire.abre, cierra: $wire.cierra,
            get final() { const c = this.pesos[this.computo]; return c && this.peso > 0 ? Math.round(c * this.peso) / 100 : null },
            get dias() {
                const a = new Date(this.abre), b = new Date(this.cierra);
                if (isNaN(a) || isNaN(b)) return null;
                return (b - a) / 86400000;
            },
        }">
        <x-ui.field :etiqueta="__('Título')" :para="'titulo'.$sufijo" error="titulo" requerido><x-ui.input :id="'titulo'.$sufijo" wire:model="titulo" /></x-ui.field>
        <x-ui.field :etiqueta="__('Instrucciones')" :para="'instrucciones'.$sufijo" error="instrucciones" :ayuda="__('Lo que el estudiante debe hacer y cómo se evalúa.')">
            <x-ui.textarea :id="'instrucciones'.$sufijo" wire:model="instrucciones" rows="5" />
        </x-ui.field>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field :etiqueta="__('Cómputo')" :para="'componente'.$sufijo" error="componente" requerido>
                <x-ui.select :id="'componente'.$sufijo" wire:model="componente" x-on:change="computo = $event.target.value">
                    <option value="">{{ __('Elija…') }}</option>
                    @foreach ($computos as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $porcentaje($c->weight_percent) }} %)</option>@endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field :etiqueta="__('Peso dentro del cómputo (%)')" :para="'peso'.$sufijo" error="peso" requerido>
                <x-ui.input :id="'peso'.$sufijo" type="number" step="0.5" min="0" max="100" wire:model="peso" x-on:input="peso = Number($event.target.value)" />
            </x-ui.field>
        </div>
        <p class="-mt-2 flex items-center gap-2 text-xs text-texto-suave" x-show="final !== null" x-cloak>
            <x-ui.icon name="information-circle" class="size-4 shrink-0" />
            <span x-text="@js(__('Equivale a ')) + final.toLocaleString('es-SV') + @js(__(' % de la nota final de la materia.'))"></span>
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field :etiqueta="__('Abre')" :para="'abre'.$sufijo" error="abre" requerido><x-ui.input :id="'abre'.$sufijo" type="datetime-local" wire:model="abre" x-on:input="abre = $event.target.value" /></x-ui.field>
            <x-ui.field :etiqueta="__('Cierra')" :para="'cierra'.$sufijo" error="cierra" requerido><x-ui.input :id="'cierra'.$sufijo" type="datetime-local" wire:model="cierra" x-on:input="cierra = $event.target.value" /></x-ui.field>
        </div>
        <p class="-mt-2 text-xs" x-show="dias !== null" x-cloak :class="dias > 0 ? 'text-texto-suave' : 'text-red-700'"
            x-text="dias <= 0 ? @js(__('El cierre debe ser después de la apertura.')) : (dias < 1 ? @js(__('Queda abierta ')) + Math.round(dias * 24) + @js(__(' horas.')) : @js(__('Queda abierta ')) + Math.round(dias) + @js(__(' días.')))"></p>
    </div>
</x-ui.card>
