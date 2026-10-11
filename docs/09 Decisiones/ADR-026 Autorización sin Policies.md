---
tipo: adr
id: ADR-026
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF03]]", "[[Matriz RBAC]]", "[[Seguridad]]", "[[Estructura del código]]", "[[ADR-017 Código modular por dominio]]"]
fuentes: []
---

# ADR-026 · Autorización sin Policies: permiso de spatie + alcance en los Services

## Contexto
El diseño original usaba spatie/laravel-permission para los permisos y Policies de Laravel para el alcance de cada rol. El equipo decidió no usar Policies.

## Decisión
*Decidido por el equipo el 10/10/2026:*
1. **Permiso:** spatie/laravel-permission.
   - La ruta o el componente Livewire se protege con el middleware `permission:<permiso>`.
   - El Service lo vuelve a exigir con `$usuario->can()`, para que la regla no dependa de la capa de entrada.
2. **Alcance:** una clase única, `App\Support\Alcance`. Cada Service la usa para:
   - **exigir** el permiso, o lanzar `AuthorizationException` (403);
   - **filtrar** la consulta según el alcance del rol en la [[Matriz RBAC]]: G global, D departamento, C carrera, S secciones que imparte, M sus matrículas, P lo propio.
3. **No se crean Policies ni Gates.** No hay carpeta `Policies` en los módulos y una prueba `arch()` lo vigila.
4. **Única excepción: Horizon.** El panel `/horizon` usa su mecanismo estándar, el Gate `viewHorizon`, definido en `App\Providers\HorizonServiceProvider`. Exige el permiso `horizon.ver` fuera de local. La prueba `arch()` permite la fachada `Gate` solo en esa clase.

```php
// Ejemplo en un Service
public function listar(User $usuario, Section $seccion): Collection
{
    $query = $this->alcance->exigir($usuario, 'asistencia.ver')
        ->aplicar(AttendanceRecord::query(), $usuario);

    return $query->where('seccion_id', $seccion->id)->get();
}
```

## Consecuencias
- Toda la autorización está en un solo lugar (`Alcance`) y en los Services, que son el único camino para leer o escribir datos.
- Las pruebas de autorización se hacen en los Services (feature): un rol permitido y uno denegado por permiso.
- La Matriz RBAC define el alcance de cada permiso por rol; `Alcance` la implementa.
