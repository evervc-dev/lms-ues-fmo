---
tipo: rol
nombre: Docente
slug: docente
estado: borrador
fuente: Roles y permisos.pdf p.3–4
---

# Rol · Docente

> **Enfoque:** Gestionar el aula virtual: diseñar e impartir contenidos, evaluar y controlar la asistencia de sus secciones.

**Alcance de datos:** Las secciones que imparte. Una sección puede tener varios docentes.

**Navegación:** [[Layout Docente]] (menú, vistas y flujos).

## Funcionalidades
### Diseño del curso
- Unidades colapsables, archivos con drag and drop, enlaces, video y texto enriquecido ([[RF11]], [[RF12]]).
- Carpetas descargables en .zip, editor de páginas HTML y papelera con 30 días de retención ([[RF11]]).
- Reutilizar contenidos de períodos anteriores ([[RF14]]).
### Evaluación y calificaciones
- Banco de preguntas por categoría, tema y dificultad ([[RF16]]).
- Tareas, rúbricas y cuestionarios con extracción aleatoria ([[RF15]], [[RF16]]).
- Política de entrega tardía por tarea ([[ADR-008 Entregas tardías]]).
- Notas por ponderación y cómputo ([[RF17]]).
### Asistencia y clases virtuales
- Abrir la sesión de asistencia con QR vigente 15 min ([[RF20]], [[ADR-002 Vigencia del QR]]).
- Código temporal de 30 s para quien no puede escanear ([[RF28]]).
- Programar clases en Google Calendar con enlace de Meet e invitación automática, y subir el reporte de asistencia de Meet al terminar ([[RF21]]).
- Solicitar la cancelación de una sesión o un cambio de modalidad ([[RF32]]).
### Comunicación
- Correos masivos a segmentos, por ejemplo «nota menor a 6.0» o «no entregó» ([[RF31]]).
- Moderar los foros del curso ([[RF30]]).
- Inscribir estudiantes en sus secciones ([[RF09]]).
- Activar la calificación anónima por evaluación ([[RF15]]).
- Publicar el acta de notas de su sección al final del período ([[RF34]]).

## Permisos
Las celdas con su alcance están en [[Matriz RBAC]].
- `contenidos.ver`
- `contenidos.gestionar`
- `contenidos.reutilizar`
- `contenidos.analitica.ver`
- `tareas.gestionar`
- `banco-preguntas.gestionar`
- `cuestionarios.gestionar`
- `rubricas.gestionar`
- `foros.participar`
- `foros.moderar`
- `calificaciones.ver`
- `calificaciones.gestionar`
- `calificaciones.corregir`
- `actas.publicar`
- `progreso.ver`
- `asistencia.sesion.abrir`
- `asistencia.codigo-temporal.generar`
- `asistencia.corregir`
- `asistencia.ver`
- `matricula.gestionar`
- `videoconferencias.gestionar`
- `clases.solicitar-cancelacion`
- `mensajes.condicionales.enviar`
- `reportes.ver`

## Notas
- El documento de roles describía un token QR de 5 s; se reemplazó por 15 minutos (ADR-002).
- La calificación anónima quedó opcional para una fase posterior ([[ADR-006 Rúbricas y calificación anónima]]).
- Con el período **cerrado**, el docente ya no corrige notas; lo hace el Coordinador ([[RF17]]).

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
