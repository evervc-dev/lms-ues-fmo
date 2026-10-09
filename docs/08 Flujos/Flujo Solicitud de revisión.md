---
tipo: flujo
nombre: Solicitud de revisión
modulo: "[[Calificaciones y progreso]]"
rf: ["[[RF35]]"]
adr: ["[[ADR-040 Solicitud de revisión de calificaciones]]"]
estado: revisado
---

# Flujo · Solicitud de revisión

```mermaid
flowchart TD
  P[Nota publicada] --> S[Estudiante: «Solicitar revisión»]
  S --> V{¿En plazo, sin otra solicitud, acta sin publicar y período abierto?}
  V -- No --> X[Mensaje con el motivo]
  V -- Sí --> M[Marca preguntas, criterios o nota general y explica cada uno]
  M --> N[Solicitud pendiente + aviso al docente]
  N --> R[Docente: revisa ítem por ítem]
  R --> D{¿Ajusta algún ítem?}
  D -- Sí --> C[Puntos nuevos → se recalcula el intento o la rúbrica → nota]
  D -- No --> K[La nota se mantiene]
  C & K --> F[Respuesta por ítem + aviso al estudiante]
```

## Reglas
- Una solicitud por actividad, sin retiro; el Coordinador solo consulta.
- Cuestionario: la corrección y la solicitud se habilitan 10 minutos después del cierre.
- El docente puede dar una prórroga del plazo para pedir revisión.
- Si el acta ya se publicó, la nota solo la corrige el Coordinador ([[RF17]]).
