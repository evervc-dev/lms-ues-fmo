---
tipo: proyecto
estado: borrador
---

# Glosario

| Término | Significado |
|---|---|
| **LMS** | Learning Management System, sistema de gestión del aprendizaje |
| **UES FMO** | Universidad de El Salvador, Facultad Multidisciplinaria de Oriente |
| **ISI** | Ingeniería de Sistemas Informáticos |
| **Período académico / ciclo** | Lapso con fechas de inicio y fin; solo uno puede estar activo ([[RF06]]) |
| **Etapas del período** | Planificación, definición de horarios y asignación docente |
| **Plan de estudios** | Plan 98 o Plan 25 |
| **Asignatura** | Materia del catálogo, con código único; se reutiliza entre períodos ([[RF07]]) |
| **Sección / grupo** | Una oferta concreta de la asignatura en un período, con horario, aula, cupos y docentes ([[RF08]]) |
| **Cómputo** | Subdivisión evaluativa del período. La nota y el 75 % de asistencia se miden por cómputo (E2, E6) |
| **Unidad** | Bloque de contenido del curso, mostrado como acordeón ([[RF11]]) |
| **Recurso** | Archivo, carpeta, página HTML, enlace o video dentro de una unidad |
| **Rúbrica** | Matriz de criterios por niveles de puntaje |
| **Banco de preguntas** | Conjunto categorizado de preguntas para extraer al azar ([[RF16]]) |
| **Fecha de gracia** | Plazo extra en el que se acepta la entrega marcada como tardía ([[ADR-008 Entregas tardías]]) |
| **Boleta / Kardex** | Consolidado de notas y asistencia del estudiante |
| **Sesión de asistencia** | Ventana que abre el docente para registrar asistencia en una clase |
| **Sesión de asistencia QR** | Ventana de 5 a 15 min con un QR cuyo token rota cada 10 s ([[ADR-028 QR rotativo]]) |
| **Token QR** | Valor del QR proyectado, válido 10 s (`qr_tokens`) |
| **Código temporal** | Código de 30 s para un solo estudiante, como respaldo del QR ([[RF28]]) |
| **Área autorizada** | Zona GPS del campus donde vale el registro ([[RF26]]) |
| **Token de dispositivo** | Valor aleatorio de 32 bytes que identifica el teléfono registrado del estudiante; el servidor guarda solo su SHA-256 y el teléfono lo conserva en una cookie y en `localStorage` ([[ADR-045 Token de dispositivo]]) |
| **student_devices** | El dispositivo registrado y activo del estudiante; solo uno ([[RF27]]) |
| **Matrícula (enrollment)** | Estudiante + sección + período + materia; a ella se ligan notas, asistencia y entregas ([[Modelo entidad-relación]]) |
| **system_settings** | Parámetros institucionales (nota mínima, redondeo, umbrales), con bandera de confirmación |
| **DbContext** | Punto único de escritura que fija el actor para la auditoría por triggers ([[ADR-027 Base de datos híbrida]]) |
| **Reporte de Meet** | Hoja de asistencia que genera Google Meet y que el docente sube ([[ADR-003 Asistencia virtual por reporte de Meet]]) |
| **Finalización** | Marca de recurso completado, manual o automática ([[RF18]]) |
| **Auto-matrícula** | Inscripción del estudiante con la clave de la sección ([[RF29]]) |
| **RBAC** | Control de acceso basado en roles ([[Matriz RBAC]]) |
| **ADR** | Architecture Decision Record, nota de decisión |
| **RTO / RPO** | Tiempo máximo de recuperación / pérdida máxima de datos ([[RNF04]]) |
