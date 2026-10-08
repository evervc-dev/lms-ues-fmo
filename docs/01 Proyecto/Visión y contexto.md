---
tipo: proyecto
estado: borrador
fuente: Instrumentos de investigación.pdf (Introducción, Conclusión)
---

# Visión y contexto

## Qué es
Una **plataforma LMS** (Learning Management System) para la carrera de **Ingeniería de Sistemas Informáticos** de la **Facultad Multidisciplinaria de Oriente (FMO)** de la Universidad de El Salvador. Reúne en un solo sistema lo que hoy está repartido entre varias herramientas: materiales, tareas, evaluaciones, calificaciones, asistencia, notificaciones y gestión académica.

## Situación actual
| Herramienta | Uso actual | Problema |
|---|---|---|
| Moodle institucional | Evaluaciones y contenido. La usa el 100 % de los docentes y el 96.4 % de los estudiantes | Servidor local: cuando falla se detiene todo. Se perdieron dos parciales ([[Entrevista E5]]). No registra asistencia |
| Google Classroom | Materiales y avisos por semana | Evaluaciones limitadas a formularios |
| Google Meet | Clases virtuales (85.7 % de los docentes) | La asistencia no se cruza con nada |
| Correo institucional | Único canal con los estudiantes (100 % de los docentes) | Sin integración |
| Papel y Excel | Asistencia (85.7 % en lista impresa) y notas (71.4 % en hoja de cálculo) | Las hojas se pierden y pasarlas a Excel toma tiempo; hay fraude en grupos numerosos |

## Problemas que resuelve
1. **Asistencia manual, lenta y con fraude.** Es el problema más repetido en las entrevistas: E1 a E7. Se resuelve con QR, GPS y dispositivo registrado, y con el reporte de Meet ([[Asistencia]]).
2. **Caídas y pérdida de información.** El 53.6 % de los estudiantes reporta caídas en horas pico ([[RNF03]], [[RNF04]]).
3. **Materiales difíciles de encontrar.** Lo reporta el 60.7 % de los estudiantes ([[RF11]]).
4. **Avisos que no llegan a tiempo.** El 96.4 % pide aviso de nuevas tareas ([[RF23]]).
5. **Trabajo repetido cada ciclo.** No se pueden reutilizar materiales ni exámenes ([[RF14]]).
6. **Gestión académica sin apoyo estadístico:** planificación, asignación docente y matrícula ([[Gestión académica]]).

## Objetivos
- Centralizar las actividades académicas en una sola plataforma web responsive.
- Automatizar el registro y el cálculo de asistencia presencial y virtual.
- Dar visibilidad del progreso y la participación al estudiante, al docente y a la jefatura.
- Garantizar disponibilidad (99 % en horario académico) y no perder evaluaciones.
- Asegurar la trazabilidad de todo cambio crítico.

## Restricciones
- Monolito Laravel + Livewire, sin API pública ([[ADR-001 Stack Laravel y Livewire]]).
- Inicio de sesión solo con Google `@ues.edu.sv` ([[ADR-007 Inicio de sesión con Google]]).
- Depende de lo que TI de la UES autorice en Google Workspace.
- Fuera de alcance: app móvil, H5P, mensajería interna. Ver [[Alcance]].

Ver también: [[Alcance]] · [[Actores]] · [[Glosario]] · [[Hallazgos]]
