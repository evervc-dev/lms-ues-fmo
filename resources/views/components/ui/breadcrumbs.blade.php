{{--
    Migas de pan. El último elemento es la página actual.
    <x-ui.breadcrumbs :migas="[['etiqueta' => 'Mis materias', 'url' => route('materias')], ['etiqueta' => 'PRN115 · 01']]" />
--}}
@props(['migas' => []])

@if ($migas !== [])
    <nav aria-label="{{ __('Migas de pan') }}" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-1 text-sm text-zinc-600">
            @foreach ($migas as $miga)
                <li class="flex items-center gap-1">
                    @if (! $loop->first)
                        <x-ui.icon name="chevron-right" class="size-4 text-zinc-400" />
                    @endif
                    @if (! $loop->last && ! empty($miga['url']))
                        <a href="{{ $miga['url'] }}" wire:navigate class="hover:text-marca-700 hover:underline">{{ $miga['etiqueta'] }}</a>
                    @else
                        <span @if ($loop->last) aria-current="page" class="font-medium text-zinc-900" @endif>{{ $miga['etiqueta'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
