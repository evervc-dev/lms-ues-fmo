{{--
    Avisos flotantes. Livewire: $this->dispatch('aviso', tipo: 'exito', mensaje: 'Entrega enviada.');
    Redirección: ->with('aviso', ['tipo' => 'exito', 'mensaje' => '…']).
--}}
<div
    x-data="{
        avisos: [],
        agregar(aviso) {
            const id = Date.now() + Math.random();
            this.avisos.push({ id, tipo: aviso.tipo ?? 'info', mensaje: aviso.mensaje });
            setTimeout(() => this.quitar(id), 6000);
        },
        quitar(id) { this.avisos = this.avisos.filter((a) => a.id !== id) },
    }"
    @if (session('aviso')) x-init="agregar(@js(session('aviso')))" @endif
    x-on:aviso.window="agregar($event.detail)"
    aria-live="polite"
    class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:left-auto sm:w-96"
>
    <template x-for="aviso in avisos" :key="aviso.id">
        <div
            x-transition
            role="status"
            class="pointer-events-auto flex w-full items-start gap-3 rounded-lg bg-white p-4 text-sm shadow-lg ring-1"
            :class="{
                'ring-emerald-300': aviso.tipo === 'exito',
                'ring-red-300': aviso.tipo === 'error',
                'ring-amber-300': aviso.tipo === 'aviso',
                'ring-zinc-200': ! ['exito', 'error', 'aviso'].includes(aviso.tipo),
            }"
        >
            <p class="flex-1 text-zinc-800" x-text="aviso.mensaje"></p>
            <button type="button" x-on:click="quitar(aviso.id)" class="text-zinc-400 hover:text-zinc-600" aria-label="{{ __('Cerrar aviso') }}">
                <x-ui.icon name="x-mark" class="size-4" />
            </button>
        </div>
    </template>
</div>
