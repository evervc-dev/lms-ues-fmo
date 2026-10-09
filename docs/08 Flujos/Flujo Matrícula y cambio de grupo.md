---
tipo: flujo
nombre: Matrícula y cambio de grupo
modulo: "[[Gestión académica]]"
rf: ["[[RF09]]", "[[RF29]]"]
estado: revisado
---

# Flujo · Matrícula y cambio de grupo

## Auto-matrícula con clave de la asignatura
La clave es **por asignatura y período** ([[ADR-032 Ajustes por el documento de roles]]): el estudiante elige la sección.
```mermaid
flowchart TD
  A[Estudiante ingresa la clave] --> B{¿Período activo y dentro del plazo?}
  B -- No --> X1[«La inscripción está cerrada»]
  B -- Sí --> C{¿Clave válida de una asignatura<br/>de su carrera y ciclo?}
  C -- No --> F[Intento fallido +1] --> L{¿5 fallos seguidos?}
  L -- Sí --> X2[Bloqueo de 15 min]
  L -- No --> X3[«Clave incorrecta»]
  C -- Sí --> D{¿Ya está matriculado en la asignatura?}
  D -- Sí --> X4[«Ya está inscrito en otra sección»]
  D -- No --> S[Muestra las secciones abiertas<br/>con cupo y sin choque de horario]
  S --> V{¿Hay alguna disponible?}
  V -- No --> X5[«No hay secciones disponibles<br/>para su horario»]
  V -- Sí --> E[El estudiante elige una sección]
  E --> R{Revalida dentro de la transacción:<br/>cupo y choque de horario}
  R -- Falla --> X6[«La sección se llenó o choca;<br/>elija otra»]
  R -- OK --> M[enrollments activa + enrollment_changes<br/>con bloqueo de la sección]
  M --> N[Notificación + auditoría]
```

## Matrícula por otros roles
Según [[ADR-030 Matrícula ampliada]], inscriben o retiran con motivo, dentro de su ámbito:
- el **Docente**, en sus secciones;
- el **Coordinador**, en su carrera;
- el **Jefe**, en su departamento;
- el **Administrador**, en cualquiera.

**Soporte Técnico no matricula**: no tiene privilegios académicos ([[ADR-032 Ajustes por el documento de roles]]).

Cada alta, retiro o traslado escribe `enrollment_changes`, un historial inmutable. Las mismas validaciones (cupo, duplicado, choque de horario) aplican a todos. **Los prerrequisitos no se validan.**

## Cambio de grupo
```mermaid
sequenceDiagram
  actor E as Estudiante
  participant L as LMS
  actor C as Coordinador
  E->>L: Solicita el cambio a la sección B con justificación
  L->>L: ¿Dentro de las 3 primeras semanas? ¿B tiene cupo y no choca de horario?
  alt No cumple
    L-->>E: Rechazo con motivo
  else Cumple
    L->>C: Solicitud pendiente
    C->>L: Aprueba
    L->>L: Transacción: A pasa a transferred, B queda active (enlazadas) + enrollment_changes
    L->>L: Historial de matrícula + auditoría
    L-->>E: Notificación
  end
```

## Reglas
- El cupo se descuenta con bloqueo de fila, para evitar sobrecupos cuando dos estudiantes se inscriben a la vez.
- El plazo de cambio (**3 semanas**) es configurable por período.
- Las entregas y notas del estudiante se conservan al cambiar de grupo: quedan en la matrícula anterior, enlazada a la nueva con `transferred_to_id`, y **la lectura recorre esa cadena** ([[ADR-062 Notas y entregas en un cambio de grupo]]). No se mueve ni se copia ninguna fila.
- Las notas se heredan **por cómputo**, no por actividad: las actividades son propias de cada sección, y lo comparable entre los dos grupos es el cómputo, que ambos copian de la plantilla del período. En cuanto el docente del grupo nuevo califica ese cómputo, manda el suyo.
- Las entregas del grupo anterior se ven de **solo lectura**, con su grupo de origen. La **asistencia no se encadena**: cada sección cuenta sus propias sesiones.
