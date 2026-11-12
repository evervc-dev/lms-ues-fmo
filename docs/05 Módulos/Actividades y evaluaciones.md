---
tipo: modulo
nombre: Actividades y evaluaciones
estado: borrador
---

# Módulo · Actividades y evaluaciones

> Las tareas y los cuestionarios con los que se evalúa.

## Qué hace
- **Tareas** individuales o grupales (una entrega por equipo), con fechas, intentos, extensiones y pesos permitidos.
- **Política de entrega por tarea**: cierre estricto, gracia sin penalización, o gracia con penalización de X %.
- **Rúbricas** con cálculo automático de la nota.
- **Bancos de preguntas** por tema y dificultad; **4 tipos** (opción múltiple, V/F, respuesta corta, ensayo).
- **Cuestionarios** con extracción aleatoria, tiempo límite, intentos, navegación lineal o libre, **autoguardado cada 30 s** y reconexión sin pérdidas.
- Retroalimentación diferida con justificación de la respuesta correcta y estadísticas por pregunta.

## Requisitos
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del módulo
    order: [file.name, nombre, prioridad, estado]
    sort:
      - property: file.name
        direction: ASC
```

## Roles
[[Docente]] · [[Estudiante]]

## Tablas
[[Modelo entidad-relación#Actividades y evaluaciones|Diagrama ER]]: `assessments`, `assignment_settings`, `quiz_settings`, `teams`, `team_members`, `submissions`, `submission_files`, `submission_events`, `submission_feedback`, `submission_rubric_scores`, `rubrics`, `question_banks`, `questions`, `question_options`, `quiz_question_rules`, `quiz_attempts`, `attempt_answers`

## Flujos
[[Flujo Entrega de tarea]] · [[Flujo Resolución de cuestionario]]

## Decisiones
[[ADR-006 Rúbricas y calificación anónima]] · [[ADR-008 Entregas tardías]] · [[ADR-009 Tipos de pregunta]]

## Notas técnicas
- El tiempo límite y la hora de cierre se controlan en el servidor.
- Prueba de carga con 100 intentos simultáneos ([[RNF10]]).
