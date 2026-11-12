{{-- Ayuda en la app (RNF05, maqueta 49). --}}
<div class="flex flex-col gap-5">
    @if ($html === null)
        <x-ui.encabezado :titulo="__('Ayuda')" :subtitulo="__('Manuales de uso para sus roles. En cada pantalla, el ícono ? abre la sección que corresponde.')" />
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($manuales as $m)
                <a href="{{ route('ayuda', ['manual' => $m['slug']]) }}" wire:navigate class="flex items-center gap-3 rounded-xl bg-white p-4 ring-1 ring-borde hover:ring-marca-300">
                    <span class="rounded-lg bg-marca-50 p-2 text-marca-600"><x-ui.icon name="book-open" /></span>
                    <span class="font-medium text-texto">{{ $m['titulo'] }}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <nav aria-label="{{ __('Contenido del manual') }}" class="hidden lg:block">
                <ol class="sticky top-24 flex flex-col gap-1 text-sm">
                    @foreach ($indice as $i)<li><a href="#{{ $i['id'] }}" class="block rounded px-2 py-1 text-texto-suave hover:bg-zinc-100 hover:text-texto">{{ $i['texto'] }}</a></li>@endforeach
                </ol>
            </nav>
            <article class="rounded-xl bg-white p-6 ring-1 ring-borde [&_a]:font-medium [&_a]:text-marca-700 [&_a]:underline [&_blockquote]:my-4 [&_blockquote]:rounded-r-lg [&_blockquote]:border-l-4 [&_blockquote]:border-marca-300 [&_blockquote]:bg-marca-50/60 [&_blockquote]:px-4 [&_blockquote]:py-3 [&_blockquote_p]:mb-1 [&_blockquote_p:last-child]:mb-0 [&_code]:rounded [&_code]:bg-zinc-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-xs [&_h1]:mb-4 [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:mb-3 [&_h2]:mt-8 [&_h2]:scroll-mt-24 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mb-2 [&_h3]:mt-6 [&_h3]:text-base [&_h3]:font-semibold [&_h4]:mb-2 [&_h4]:mt-4 [&_h4]:font-semibold [&_hr]:my-6 [&_hr]:border-borde [&_li]:mb-1.5 [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mb-3 [&_pre]:mb-4 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-zinc-50 [&_pre]:p-3 [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_strong]:font-semibold [&_strong]:text-texto [&_table]:mb-4 [&_table]:w-full [&_td]:border [&_td]:border-borde [&_td]:p-2 [&_th]:border [&_th]:border-borde [&_th]:bg-zinc-50 [&_th]:p-2 [&_th]:text-left [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:pl-6 text-sm leading-relaxed text-texto">
                {!! $html !!}
            </article>
        </div>
    @endif
</div>
