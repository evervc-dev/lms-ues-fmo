---
tipo: rol
nombre: Estudiante
slug: estudiante
estado: borrador
fuente: Roles y permisos.pdf p.2–3
---

# Rol · Estudiante

> **Enfoque:** Consumir contenidos, entregar actividades y evaluaciones, y registrar su asistencia presencial y virtual.

**Alcance de datos:** Lo propio, y las asignaturas donde está matriculado.

**Navegación:** [[Layout Estudiante]] (menú, vistas y flujos).

## Funcionalidades
### Panel y progreso
- Tarjetas de asignaturas del ciclo con barra de avance ([[RF24]], [[RF18]]).
- Próximas entregas y exámenes en orden cronológico.
- Indicadores de finalización manual o automática ([[RF18]]).
### Entregas, evaluaciones y foros
- Enviar tareas con validación de peso y extensión, a tiempo o en gracia ([[RF15]]).
- Resolver cuestionarios con preguntas aleatorias, tiempo límite y autoguardado ([[RF16]]).
- Participar en foros con hilos anidados ([[RF30]]).
### Asistencia y dispositivo
- Escanear el QR de la sesión (vigente 15 min) desde su dispositivo registrado, con GPS ([[RF20]]).
- Asistencia virtual: Presente si el reporte de Meet que sube el docente muestra al menos 70 % de la clase ([[RF21]]).
- Historial de asistencia con alerta cerca del límite de faltas ([[RF22]]).
- Auto-matricularse con la clave de la sección ([[RF29]]).
- Registrar su teléfono (token de dispositivo) y solicitar el cambio si pierde el teléfono ([[RF27]]).
- Solicitar un cambio de grupo dentro de las 3 primeras semanas ([[RF09]]).
### Notas
- Ver su boleta o kardex por cómputo, unidad y peso ([[RF17]]).

## Permisos
Las celdas con su alcance están en [[Matriz RBAC]].
- `contenidos.ver`
- `tareas.entregar`
- `cuestionarios.resolver`
- `foros.participar`
- `calificaciones.ver`
- `progreso.ver`
- `asistencia.marcar`
- `asistencia.ver`
- `dispositivo.registrar`
- `dispositivo.solicitar-cambio`
- `matricula.automatricular`
- `matricula.solicitar-cambio`

## Requisitos donde participa
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF del rol
    order: [file.name, nombre, prioridad, modulo, estado]
```
