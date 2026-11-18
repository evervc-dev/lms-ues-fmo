{{-- Cuenta elegida en «Entrar como…» y rol con el que se va a entrar. Variables: $cuenta, $roles. --}}
<div class="flex flex-col gap-4">
    <div>
        <p class="truncate text-sm font-semibold text-zinc-900">{{ $cuenta->fullName() }}</p>
        <p class="truncate text-xs text-zinc-500">{{ $cuenta->institutional_email }}</p>
        <div class="mt-1 flex flex-wrap gap-1">
            @forelse ($cuenta->roles as $rolActual)
                <x-ui.badge color="marca">{{ __('roles.'.$rolActual->name) }}</x-ui.badge>
            @empty
                <x-ui.badge>{{ __('Sin rol') }}</x-ui.badge>
            @endforelse
        </div>
    </div>

    <x-ui.field :etiqueta="__('Entrar con el rol')" para="cambio-de-rol-rol" error="rol" :ayuda="__('Deje «Dejar sus roles actuales» para entrar sin tocar la cuenta.')">
        <x-ui.select id="cambio-de-rol-rol" wire:model="rol">
            <option value="">{{ __('Dejar sus roles actuales') }}</option>
            @foreach ($roles as $valor => $etiqueta)
                <option value="{{ $valor }}">{{ $etiqueta }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>

    <x-ui.alert tipo="aviso">{{ __('Al elegir un rol se reemplazan los roles de la cuenta por ese, solo en este entorno de pruebas. No se crean asignaciones: con Docente, Coordinador o Jefe de Departamento la cuenta puede quedar sin secciones, carrera ni departamento. Podrá devolverle sus roles con «Restaurar» en el menú de usuario.') }}</x-ui.alert>
</div>
