---
tipo: flujo
nombre: Asistencia con QR
modulo: "[[Asistencia]]"
rf: ["[[RF20]]", "[[RF26]]", "[[RF27]]", "[[RF19]]"]
adr: ["[[ADR-028 QR rotativo]]", "[[ADR-027 Base de datos híbrida]]"]
estado: revisado
---

# Flujo · Asistencia presencial con QR

**Actores:** [[Docente]], [[Estudiante]]. **Precondición:** el estudiante está matriculado y tiene un dispositivo registrado ([[Flujo Registro y cambio de dispositivo]]).

## Apertura y rotación del token
```mermaid
sequenceDiagram
  actor D as Docente
  participant L as LMS
  participant DB as PostgreSQL
  D->>L: Abrir asistencia (sección, clase de hoy, vigencia 5-15 min)
  L->>DB: INSERT attendance_sessions (status open, qr_validity_minutes, qr_rotation_seconds del parámetro)
  loop Cada 2 s mientras la sesión está abierta (wire:poll)
    L->>DB: INSERT qr_tokens una vez por rotación (token_hash, expires_at = fin de la ventana)
    L-->>D: El QR proyectado se actualiza
  end
  Note over D,L: El docente puede cerrar antes de que venza la sesión
```

## Escaneo y validaciones
El Service `AsistenciaQrService` corre dentro de `DbContext` (una transacción). **Siempre confirma**, porque los intentos rechazados también deben quedar guardados.

```mermaid
flowchart TD
  S[Estudiante escanea el QR con su teléfono] --> G{¿El navegador entrega la ubicación?}
  G -- No --> X0[no_location<br/>«Active el GPS o pida un código temporal»]
  G -- Sí --> V{¿Sesión abierta y dentro de su vigencia?}
  V -- No --> X1[session_closed]
  V -- Sí --> T{¿Token vigente de esta sesión?<br/>rota cada 10 s}
  T -- No --> X2[token_expired<br/>«Escanee el QR actual»]
  T -- Sí --> L{¿Le quedan intentos?<br/>máximo 3 por sesión}
  L -- No --> X3[scan_limit<br/>«Pida un código temporal»]
  L -- Sí --> M{¿Matrícula activa en la sección?}
  M -- No --> X4[not_enrolled]
  M -- Sí --> R{¿Ya tiene registro en esta sesión?}
  R -- Sí --> X5[already_recorded]
  R -- No --> A{¿Distancia al área ≤ radio?}
  A -- No --> X6[out_of_range]
  A -- Sí --> W{¿Token de dispositivo válido del dispositivo activo?}
  W -- No --> X7[invalid_device]
  W -- Sí --> OK[attendance_records: present<br/>method=qr, hora del servidor, distancia, precisión]
  X0 & X1 & X2 & X3 & X4 & X5 & X6 & X7 & OK --> LOG[attendance_scan_attempts<br/>resultado exacto, inmutable]
```

## Reglas
- `UNIQUE (session_id, enrollment_id)` impide duplicados, incluso con doble clic o reintentos.
- La hora que vale es la del **servidor**. El token de dispositivo lo verifica Laravel (compara su SHA-256 con el del dispositivo activo, [[ADR-045 Token de dispositivo]]); PostgreSQL no puede hacerlo.
- **Tardanza:** con QR no hay tardanza: toda marca aceptada queda `present` ([[ADR-046 Lector QR en la página y rotación por polling]]). La tardanza (0,5 en el porcentaje, [[ADR-030 Matrícula ampliada]]) queda para el registro manual y el código temporal.
- **Token del QR:** vale el vigente y el inmediatamente anterior; se deriva con HMAC de la sesión y la ventana de rotación, y solo se guarda su hash ([[ADR-046 Lector QR en la página y rotación por polling]]).
- No se guardan coordenadas: solo la distancia, la precisión y si estaba dentro del área.
- Quien no puede escanear: [[Flujo Código temporal]] o marcado manual (auditado).
- Al cerrar la sesión, los matriculados sin registro quedan `absent`.
- Los tokens vencidos se purgan a los 7 días; los intentos se conservan.
