---
tipo: adr
id: ADR-028
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RF20]]", "[[Flujo Asistencia con QR]]", "[[Modelo entidad-relación]]"]
fuentes: ["Diseño de BD de @Alex, sección 17.1 #1", "Roles y permisos.pdf p.3", "Requerimientos.pdf p.10"]
---

# ADR-028 · Sesión QR configurable con token rotativo

**Reemplaza a [[ADR-002 Vigencia del QR]]** (QR fijo de 15 min).

> [!note] Parte WebAuthn reemplazada por [[ADR-045 Token de dispositivo]]
> El dispositivo se valida con un token de dispositivo propio; el resto de esta ADR sigue vigente.

## Decisión
*Decidido por el equipo el 10/10/2026:*
- La **sesión de asistencia dura de 5 a 15 minutos**, configurable por el docente (`attendance_sessions.qr_validity_minutes`, con `CHECK` entre 5 y 15).
- El **token del QR rota cada 10 segundos**. Es un parámetro de 5 a 30 s (`qr_rotation_seconds`); cada token se guarda en `qr_tokens` con `expires_at`.
- Cada estudiante tiene **hasta 3 intentos por sesión** (`max_scans_per_student`, parámetro).
- **Todo intento**, exitoso o no, queda en `attendance_scan_attempts` con su motivo exacto.

## Consecuencias
- El docente debe **proyectar el QR en pantalla** durante la sesión: una foto enviada por WhatsApp caduca en 10 s.
- Se suman al GPS y al token de dispositivo ([[ADR-045 Token de dispositivo]]). El riesgo de compartir el QR baja mucho, aunque no desaparece.
- Los tokens vencidos se purgan (7 días); los intentos se conservan.
