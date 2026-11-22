{{-- Acceso local de la cuenta superadministradora (ADR-082). --}}
<form wire:submit="entrar" class="flex flex-col gap-5">
    <p class="text-sm text-zinc-600">{{ __('Acceso con contraseña solo para la cuenta superadministradora. Las demás cuentas entran con Google.') }}</p>

    <x-ui.field :etiqueta="__('Correo')" para="correo" error="correo" requerido>
        <x-ui.input id="correo" type="email" wire:model="correo" autocomplete="username" autofocus />
    </x-ui.field>

    <x-ui.field :etiqueta="__('Contraseña')" para="clave" error="clave" requerido>
        <x-ui.input id="clave" type="password" wire:model="clave" autocomplete="current-password" />
    </x-ui.field>

    <x-ui.button type="submit" class="w-full py-2.5">{{ __('Entrar') }}</x-ui.button>
</form>
