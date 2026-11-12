{{--
    Campos de asignación de un rol (RF04).
    Variables: $rolAsignacion, $opciones, $carneFijo, $prefijoError y las propiedades del componente.
    $prefijoError es la clave de los errores del Service: «asignacion» (crear) o «asignaciones.<rol>» (cambiar roles);
    sus errores de campo llegan como «<prefijo>.<campo>».
--}}
@error($prefijoError)
    <p class="text-xs font-medium text-red-700">{{ $message }}</p>
@enderror

@php($relevos = $this->relevos)

@switch($rolAsignacion)
    @case('coordinador')
        <x-ui.field :etiqueta="__('Carrera que coordina')" para="carreraCoordina" error="carreraCoordina" :ayuda="__('Si la carrera ya tiene coordinador, se le cierra la vigencia hoy y pierde el rol si no coordina otra.')" requerido>
            <x-ui.select id="carreraCoordina" wire:model.live="carreraCoordina">
                <option value="">{{ __('Elija…') }}</option>
                @foreach ($opciones['carreras'] as $c)
                    <option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>
                @endforeach
            </x-ui.select>
            @error($prefijoError.'.carrera')
                <p class="text-xs font-medium text-red-700">{{ $message }}</p>
            @enderror
        </x-ui.field>
        @isset($relevos['coordinador'])
            @include('modules.identidad.partials.relevo', ['rol' => 'coordinador', 'titular' => $relevos['coordinador']])
        @endisset
        @break

    @case('jefe_departamento')
        <x-ui.field :etiqueta="__('Departamento que dirige')" para="departamentoJefatura" error="departamentoJefatura" :ayuda="__('Si el departamento ya tiene jefe, se le cierra la vigencia hoy y pierde el rol si no dirige otro.')" requerido>
            <x-ui.select id="departamentoJefatura" wire:model.live="departamentoJefatura">
                <option value="">{{ __('Elija…') }}</option>
                @foreach ($opciones['departamentos'] as $d)
                    <option value="{{ $d->id }}">{{ $d->code }} · {{ $d->name }}</option>
                @endforeach
            </x-ui.select>
            @error($prefijoError.'.departamento')
                <p class="text-xs font-medium text-red-700">{{ $message }}</p>
            @enderror
        </x-ui.field>
        @isset($relevos['jefe_departamento'])
            @include('modules.identidad.partials.relevo', ['rol' => 'jefe_departamento', 'titular' => $relevos['jefe_departamento']])
        @endisset
        @break

    @case('docente')
        <x-ui.field :etiqueta="__('Departamento del docente')" para="departamentoDocente" error="departamentoDocente" requerido>
            <x-ui.select id="departamentoDocente" wire:model="departamentoDocente">
                <option value="">{{ __('Elija…') }}</option>
                @foreach ($opciones['departamentos'] as $d)
                    <option value="{{ $d->id }}">{{ $d->code }} · {{ $d->name }}</option>
                @endforeach
            </x-ui.select>
            @error($prefijoError.'.departamento')
                <p class="text-xs font-medium text-red-700">{{ $message }}</p>
            @enderror
        </x-ui.field>
        @break

    @case('estudiante')
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field :etiqueta="__('Carrera')" para="carreraEstudiante" error="carreraEstudiante" requerido>
                <x-ui.select id="carreraEstudiante" wire:model.live="carreraEstudiante">
                    <option value="">{{ __('Elija…') }}</option>
                    @foreach ($opciones['carreras'] as $c)
                        <option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>
                    @endforeach
                </x-ui.select>
                @error($prefijoError.'.carrera')
                    <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                @enderror
            </x-ui.field>
            <x-ui.field :etiqueta="__('Plan de estudios')" para="planEstudiante" error="planEstudiante" requerido>
                <x-ui.select id="planEstudiante" wire:model="planEstudiante" :disabled="! $carreraEstudiante">
                    <option value="">{{ __('Elija…') }}</option>
                    @foreach ($opciones['planes']->where('career_id', $carreraEstudiante) as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </x-ui.select>
                @error($prefijoError.'.plan')
                    <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                @enderror
            </x-ui.field>
            <x-ui.field :etiqueta="__('Ciclo')" para="cicloEstudiante" error="cicloEstudiante" requerido>
                <x-ui.input id="cicloEstudiante" type="number" min="1" :max="\App\Modules\Identidad\Services\AsignacionService::CICLO_MAXIMO" wire:model="cicloEstudiante" />
                @error($prefijoError.'.ciclo')
                    <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                @enderror
            </x-ui.field>
            @php($carneDelCorreo = $this->carneDelCorreo)
            <x-ui.field :etiqueta="__('Carné')" para="carne" error="carne" :requerido="! $carneFijo && $carneDelCorreo === null"
                :ayuda="match (true) {
                    $carneFijo => __('El carné no se puede modificar.'),
                    $carneDelCorreo !== null => __('Se toma del correo institucional y no se podrá modificar después.'),
                    default => __('Se toma del correo institucional; escríbalo solo si el correo no tiene un carné válido.'),
                }">
                @if ($carneDelCorreo !== null)
                    <x-ui.input id="carne" :value="$carneDelCorreo" disabled />
                @else
                    <x-ui.input id="carne" wire:model="carne" maxlength="20" :disabled="$carneFijo" placeholder="gs21004" />
                @endif
                @error($prefijoError.'.carne')
                    <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                @enderror
            </x-ui.field>
        </div>
        @break
@endswitch
