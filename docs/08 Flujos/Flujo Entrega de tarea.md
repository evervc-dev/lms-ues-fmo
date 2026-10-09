---
tipo: flujo
nombre: Entrega de tarea
modulo: "[[Actividades y evaluaciones]]"
rf: ["[[RF15]]"]
adr: ["[[ADR-008 Entregas tardías]]"]
estado: revisado
---

# Flujo · Entrega de tarea

```mermaid
flowchart TD
  S[Estudiante pulsa Enviar] --> O{¿La tarea está abierta?}
  O -- Aún no --> X0[«La tarea aún no abre»]
  O -- Sí --> V{¿Extensión y peso permitidos?}
  V -- No --> X1[Rechazo con los formatos y el peso permitidos]
  V -- Sí --> I{¿Le quedan intentos?}
  I -- No --> X2[«No le quedan intentos»]
  I -- Sí --> H{Hora del servidor frente al cierre}
  H -- "≤ cierre" --> OK[Entrega A TIEMPO]
  H -- "> cierre" --> P{Política de la tarea}
  P -- Cierre estricto --> X3[Rechazo: «Plazo vencido»]
  P -- Gracia --> G{¿Dentro de la gracia?}
  G -- No --> X3
  G -- Sí --> T{¿Tiene penalización?}
  T -- No --> L1[Entrega TARDÍA sin descuento]
  T -- "Sí, X %" --> L2[Entrega TARDÍA con descuento de X % al calificar]
  OK & L1 & L2 --> N[Archivo guardado + historial + notificación al docente si lo configuró]
```

## Reglas
- **Grupal:** una entrega por equipo; cualquier integrante entrega y vale para todos.
- El estudiante puede **cancelar** la entrega mientras la tarea esté abierta; queda en el historial.
- El descuento se aplica **al calificar** y se muestra al estudiante: nota = nota de la rúbrica × (1 − X %).
- La hora de corte es la del servidor.
