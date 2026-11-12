{{-- Cabecera y pestañas de la Vista de sección para las pantallas Livewire (trait PaginaDeSeccion). --}}
@props(['resumen', 'pestanas'])

<x-seccion.cabecera :seccion="$resumen">
    <x-slot:acciones>{{ $acciones ?? '' }}</x-slot:acciones>
</x-seccion.cabecera>
<x-seccion.pestanas :pestanas="$pestanas" />
