---
tipo: flujo
nombre: Código temporal
modulo: "[[Asistencia]]"
rf: ["[[RF28]]"]
estado: revisado
---

# Flujo · Código temporal de asistencia

Es el respaldo del QR para un estudiante concreto: sin teléfono registrado, sin GPS o con una falla.

```mermaid
sequenceDiagram
  actor D as Docente
  participant L as LMS
  actor E as Estudiante
  D->>L: Generar código para el estudiante X (sesión abierta)
  L->>L: ¿X matriculado y sin registro en la sesión?
  alt No
    L-->>D: Rechazo con motivo
  else Sí
    L->>L: temporary_attendance_codes: 6 dígitos (hash), solo para X, vence en 30 s
    L-->>D: Muestra el código con una cuenta regresiva
    D-->>E: Le dicta el código
    E->>L: Ingresa el código desde su sesión
    L->>L: ¿Mismo estudiante? ¿No usado? ¿Menos de 30 s?
    alt Válido
      L->>L: attendance_records present, method=code, código marcado como usado (UPDATE atómico)
      L-->>E: Asistencia registrada
    else Inválido
      L-->>E: «Código inválido o vencido»
    end
  end
```

## Reglas
- El código está ligado a **una cuenta**: otro estudiante no puede usarlo aunque lo vea.
- Es de un solo uso y vence a los 30 s; el docente puede generar otro.
- No exige GPS ni dispositivo: la presencia la avala el docente, y queda como evidencia del método.
