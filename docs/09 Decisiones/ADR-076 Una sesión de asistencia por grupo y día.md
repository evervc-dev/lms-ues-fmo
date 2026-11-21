---
tipo: adr
id: ADR-076
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF19]]", "[[RF20]]", "[[RF21]]", "[[RF22]]", "[[Docente]]"]
fuentes: ["Decisión del usuario del 21/11/2026 (D3 de la revisión de flujos)"]
---

# ADR-076 · Una sesión de asistencia por grupo y día

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- Se podían crear dos sesiones el mismo día en un grupo: con dos clics seguidos en «Abrir asistencia», o cerrando antes de tiempo y volviendo a abrir. Quien marcaba en una quedaba ausente en la otra y el porcentaje contaba dos clases.
- También se podía abrir a mano una sesión «Virtual» y después confirmar el reporte de Meet del mismo día: la clase contaba doble.

## Opciones
- **A. Una sola sesión por día, sin reabrir:** lo que falte se corrige a mano, con motivo.
- **B. Reabrir la misma sesión** sin perder lo marcado.

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- `AsistenciaService::abrir` rechaza una segunda sesión del grupo con la misma `session_date`.
- Índice único parcial `attendance_sessions_single_open_unique` (una sola sesión `open` por grupo), en SQLite y PostgreSQL: cierra la carrera de dos clics.
- Una sesión abierta a mano siempre es presencial; la asistencia virtual se registra solo con el reporte de Meet ([[ADR-003 Asistencia virtual por reporte de Meet]]).

## Consecuencias
- Las pruebas que abrían dos sesiones el mismo día pasan al día siguiente.
- La marca de entrada y salida en clases presenciales (pedida el mismo día) vivirá dentro de esa única sesión; queda para una ADR propia.
