---
tipo: flujo
nombre: Registro y cambio de dispositivo
modulo: "[[Identidad y acceso]]"
rf: ["[[RF27]]"]
estado: revisado
---

# Flujo · Registro y cambio de dispositivo

**Actores:** [[Estudiante]], [[Soporte Técnico]].

## Primer registro
```mermaid
sequenceDiagram
  actor E as Estudiante (en su teléfono)
  participant L as LMS
  participant A as Navegador del teléfono
  E->>L: Mi perfil → Registrar este teléfono
  L->>L: ¿Tiene dispositivo activo?
  alt Ya tiene uno
    L-->>E: «Ya tiene un teléfono registrado. Solicite el cambio.»
  else No tiene
    L->>L: Genera el token de dispositivo (32 bytes aleatorios)
    L->>L: student_devices activo con el SHA-256 del token + device_events + auditoría
    L-->>A: Cookie device_token (1 año) + copia en localStorage
    L-->>E: Teléfono registrado
  end
```

## Cambio de teléfono
```mermaid
flowchart TD
  S[Estudiante, con su sesión de Google @ues.edu.sv,<br/>solicita el cambio con un motivo] --> C{¿Hubo un cambio<br/>en los últimos 30 días?}
  C -- Sí --> J[Debe adjuntar una justificación extra]
  C -- No --> P
  J --> P[Solicitud pendiente para Soporte]
  P --> A[Aviso a quienes atienden la bandeja<br/>device_request.pending + contador del menú]
  A --> V{Soporte revisa}
  V -- Aprueba --> R[Revoca el dispositivo anterior + device_events + auditoría]
  R --> N[Notifica al estudiante: puede registrar el nuevo]
  V -- Rechaza --> X[Notifica al estudiante con el motivo<br/>que queda en resolution_note]
  N --> Q[Aviso de cierre al resto del equipo<br/>device_request.resolved]
  X --> Q
```

## Reglas
- La identidad del estudiante la garantiza su sesión de Google `@ues.edu.sv`; Soporte solo da el visto bueno.
- Máximo **un cambio cada 30 días** sin justificación extra.
- El servidor guarda solo el hash del token; la cookie `device_token` y su copia en `localStorage` valen si cualquiera coincide con el dispositivo activo ([[ADR-045 Token de dispositivo]]). Si se pierden ambas, el estudiante solicita el cambio.
- Los avisos de la bandeja van a quien tiene `dispositivos.gestionar`, no a un rol fijo. A quien resuelve no
  se le avisa de su propio cierre, y el motivo del rechazo queda guardado para verlo en «Resueltas».
- Es una solicitud puntual, no una mesa de ayuda general (P08 no se aprobó).
- Mientras no tenga dispositivo, el estudiante se registra con código temporal o de forma manual ([[RF28]], [[RF19]]).
