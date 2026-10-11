---
tipo: adr
id: ADR-042
estado: pendiente
fecha: 2026-10-18
afecta: ["[[RF16]]", "[[RNF10]]", "[[ADR-010 Notificaciones en tiempo real]]", "[[ADR-040 Solicitud de revisión de calificaciones]]"]
fuentes: ["Decisión del equipo (18/10/2026)"]
---

# ADR-042 · Flujo del examen sin depender de Reverb

## Contexto
[[ADR-010 Notificaciones en tiempo real]] proponía usar Reverb también «para el contador y el autoguardado de los exámenes». Pero el contador debe anclarse al servidor y seguir corriendo sin conexión, y el autoguardado va del navegador al servidor (HTTP), que Reverb no cubre. Además faltaban tres cosas:
- un intento abandonado (navegador cerrado) quedaba «En curso» y sin nota;
- se podían perder hasta 30 s de respuestas;
- el docente no veía el examen en vivo.

## Decisión
*Definida por el equipo el 18/10/2026; pendiente de aceptación.*

1. **El examen no depende de Reverb.**
   - **Tiempo:** el servidor fija el límite (`deadline_at`) y rechaza respuestas después de él.
   - **Contador:** es local y se resincroniza con el servidor en cada respuesta.
   - **Contador en cero:** pregunta al servidor antes de enviar, por si el docente dio minutos extra.
2. **Cierre automático:** un job programado cada minuto (`CerrarIntentosVencidos`, idempotente) corrige y envía los intentos vencidos sin envío. Antes de cerrar vuelve a comprobar el límite bajo bloqueo, así que no cierra un intento al que el docente acaba de dar minutos. Si un intento falla, no detiene a los demás.
   - Una respuesta solo se guarda si el intento sigue en curso y dentro del límite, en la misma sentencia.
   - En modo una a una solo se puede cambiar la pregunta actual.
3. **Autoguardado:**
   - cada respuesta se guarda al cambiarla (los textos, 2 s después de dejar de escribir), con el `poll` de 30 s como respaldo;
   - aviso «Sin conexión» (`wire:offline`);
   - advertencia al cerrar la pestaña.
4. **Monitor del docente** (`wire:poll.15s`, solo mientras hay algo en curso):
   - muestra sin empezar, en curso y enviados, con respondidas y tiempo restante;
   - **cerrar un intento**, que se corrige con lo guardado;
   - **dar minutos extra** a uno, a varios o a todos los intentos en curso, **hasta 30 minutos después del cierre**. Si un intento ya venció y el job aún no lo cerró, los minutos cuentan desde ese momento.
5. **Corrección:** se muestra 10 minutos después del **fin efectivo**, el mayor entre el cierre y el límite del último intento **aún en curso** con tiempo extra. Así nadie la ve mientras otro sigue respondiendo, y los intentos ya enviados no la retrasan.
6. **Auditoría:** `quiz_attempts` queda auditada por trigger, con el motivo del cierre en `event_type`:
   - `quiz_attempt.submitted` (el estudiante);
   - `quiz_attempt.expired` (vencido);
   - `quiz_attempt.closed_by_teacher`;
   - `quiz_attempt.extended` (minutos extra).

   La tabla no tiene `section_id`, así que el Jefe no ve estas filas por departamento ([[ADR-029 Auditoría departamental del Jefe]]); sí las ve el Administrador.

## Consecuencias
- Reverb queda para avisos y, más adelante, para que el monitor reciba eventos (`IntentoIniciado`, `IntentoEnviado`) con el `poll` como respaldo, cuando se implemente [[RF23]].
- El cierre automático necesita el programador (`schedule:work` en desarrollo, cron en el servidor).
- Carga estimada: 500 estudiantes con `poll` de 30 s son unas 17 peticiones por segundo ([[RNF03]]).
