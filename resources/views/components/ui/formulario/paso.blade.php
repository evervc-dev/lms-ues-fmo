{{--
    Un paso del formulario: se dibuja solo cuando es el paso actual. El número lo toma de <x-ui.formulario>
    con @aware, así la vista no tiene que repetirlo.

    <x-ui.formulario.paso :numero="2">
        <x-ui.card>…</x-ui.card>
    </x-ui.formulario.paso>

    Los campos de los pasos que no se ven no se dibujan, pero sus valores siguen en el componente Livewire:
    volver atrás no pierde nada.

    Con `:paso="0"` en <x-ui.formulario> no hay pasos y se dibujan todos: es el modo de solo lectura.
--}}
@aware(['paso' => 1])
@props(['numero'])

@if ((int) $paso === 0 || (int) $paso === (int) $numero)
    <div {{ $attributes->class(['flex flex-col gap-5']) }}>
        {{ $slot }}
    </div>
@endif
