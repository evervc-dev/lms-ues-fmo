---
tipo: adr
id: ADR-041
estado: pendiente
fecha: 2026-10-18
afecta: ["[[ADR-031 Modelo de datos v2 en inglés]]", "[[RF15]]", "[[RF16]]", "[[RF35]]", "[[RF05]]"]
fuentes: ["Decisión del equipo (18/10/2026)"]
---

# ADR-041 · La app trabaja en la hora de El Salvador

## Contexto
`config/app.php` tenía la zona fija en `UTC` e ignoraba `APP_TIMEZONE`. Los formularios con fecha y hora (nueva tarea, nuevo cuestionario) guardaban como UTC la hora que el docente escribía en hora de El Salvador. Las vistas, en cambio, la convertían a hora local. Resultado: una tarea con cierre a las 23:59 se mostraba y cerraba a las 17:59. Lo mismo pasaba con los filtros por día de la auditoría y el vencimiento de las claves.

## Decisión
*Definida por el equipo el 18/10/2026; pendiente de aceptación.*

1. **La app usa `APP_TIMEZONE`** (`America/El_Salvador` por defecto) en `config/app.php`. `now()`, los plazos, los formularios, los jobs y las vistas trabajan en hora local.
2. **La sesión de PostgreSQL usa la misma zona** (`'timezone'` en la conexión `pgsql`). Laravel envía las fechas sin desfase y PostgreSQL las interpreta en esa zona. `timestamptz` sigue guardando el **instante exacto** (internamente en UTC), como pide [[ADR-031 Modelo de datos v2 en inglés]].
3. **No se convierte a mano a UTC** (`->utc()`) antes de guardar. Eso, con la app en hora local, grabaría la hora corrida.

## Consecuencias
- Los entornos (local, Coolify y producción) deben tener `APP_TIMEZONE=America/El_Salvador`.
- **SQLite** (desarrollo y pruebas) guarda las fechas como texto en hora local. Una base creada antes de este cambio queda corrida 6 horas: se rehace con `php artisan migrate:fresh --seed`.
- El Salvador no tiene horario de verano, así que no hay horas ambiguas.
