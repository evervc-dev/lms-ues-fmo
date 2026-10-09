---
tipo: flujo
nombre: Resolución de cuestionario
modulo: "[[Actividades y evaluaciones]]"
rf: ["[[RF16]]"]
rnf: ["[[RNF10]]"]
adr: ["[[ADR-009 Tipos de pregunta]]"]
estado: revisado
---

# Flujo · Resolución de cuestionario

```mermaid
sequenceDiagram
  actor E as Estudiante
  participant L as LMS
  E->>L: Comenzar intento
  L->>L: ¿Abierto? ¿Le quedan intentos?
  L->>L: Crea Intento, sortea N preguntas del banco y las guarda en el intento
  L->>L: Calcula vence_en = min(inicio + límite, cierre del cuestionario)
  L-->>E: Primera pregunta y tiempo restante (según el servidor)
  loop Cada 30 s y al cambiar de pregunta
    E->>L: Guarda respuestas
    L-->>E: OK + tiempo restante
  end
  Note over E,L: Si se corta la red, el navegador guarda localmente y reintenta
  E-->>L: Reconexión
  L-->>E: Recupera el intento con sus respuestas guardadas
  alt El estudiante envía
    E->>L: Enviar
  else Vence el tiempo
    L->>L: Job cierra el intento con lo guardado
  end
  L->>L: Califica opción múltiple, V/F y respuesta corta
  L->>L: Ensayo → pendiente de calificar (bandeja del docente)
```

## Reglas
- Las preguntas sorteadas **se fijan en el intento**: al recargar no cambian.
- Navegación «una a una»: el servidor rechaza respuestas a preguntas anteriores.
- La revisión (respuestas y justificación) se muestra solo cuando el docente la publica.
- Mientras tenga ensayos sin calificar, el intento queda «pendiente de calificar».
- Carga: 100 intentos simultáneos con `p95 < 2 s` ([[Pruebas de carga]]).
