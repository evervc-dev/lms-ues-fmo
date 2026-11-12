{{--
    Aviso de relevo (RF04): el rol elegido solo admite un titular por unidad y ya hay uno vigente.
    Variables: $rol ('coordinador' o 'jefe_departamento') y $titular (AsignacionService::titularARelevar()).
--}}
<x-ui.alert tipo="aviso" :titulo="__('Se reemplazará a :nombre', ['nombre' => $titular->nombre])" wire:key="relevo-{{ $rol }}-{{ $titular->id }}">
    {{ $rol === 'coordinador'
        ? __(':nombre (:correo) es el coordinador vigente de esta carrera. Al guardar, su coordinación se cierra hoy y pierde el rol Coordinador de Carrera; conserva sus otros roles.', ['nombre' => $titular->nombre, 'correo' => $titular->correo])
        : __(':nombre (:correo) es el jefe vigente de este departamento. Al guardar, su jefatura se cierra hoy y pierde el rol Jefe de Departamento; conserva sus otros roles.', ['nombre' => $titular->nombre, 'correo' => $titular->correo]) }}
</x-ui.alert>
