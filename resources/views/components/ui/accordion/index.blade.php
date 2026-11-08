{{-- Contenedor de acordeones: <x-ui.accordion><x-ui.accordion.item titulo="Unidad 1">…</x-ui.accordion.item></x-ui.accordion> --}}
<div {{ $attributes->class(['divide-y divide-zinc-200 overflow-hidden rounded-xl bg-white ring-1 ring-zinc-200']) }}>
    {{ $slot }}
</div>
