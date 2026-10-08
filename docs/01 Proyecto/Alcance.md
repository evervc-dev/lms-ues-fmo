---
tipo: proyecto
estado: revisado
fuente: Alcance - Aula virtual.pdf
---

# Alcance

Sale de `Alcance - Aula virtual.pdf` y está ajustado a las decisiones del 10/10/2026 (ver [[00 Plan de documentación]]).

## Incluye
| Área | Funciones | RF |
|---|---|---|
| Usuarios, roles y seguridad | Alta manual y carga masiva CSV; RBAC con 6 roles; auditoría; inicio de sesión con Google `@ues.edu.sv` | [[RF01]], [[RF03]], [[RF04]], [[RF05]] |
| Matrícula | Manual y auto-matrícula con clave, limitada a la carrera y el ciclo | [[RF09]], [[RF29]] |
| Estructura del curso | Unidades colapsables tipo acordeón; papelera de 30 días | [[RF11]] |
| Recursos | Archivos con drag and drop (descarga o visor), carpetas descargables en .zip, enlaces de video incrustados, editor HTML | [[RF11]], [[RF12]] |
| Evaluación | Bancos de preguntas con extracción aleatoria (p. ej. 10 de 50); 4 tipos de pregunta; tiempo, intentos y navegación; tareas con cierre estricto, gracia o penalización; rúbricas | [[RF15]], [[RF16]], [[RF17]] |
| Comunicación | Foros anidados; mensajería condicional por correo; notificaciones en la plataforma y por correo | [[RF23]], [[RF30]], [[RF31]] |
| Calendario y clases virtuales | Google Calendar con enlace de Meet e invitación a los matriculados | [[RF21]] |
| Asistencia | QR de 15 min con GPS y dispositivo; código temporal; reporte de Meet (70 %); clases canceladas | [[RF19]]–[[RF22]], [[RF26]]–[[RF28]], [[RF32]] |
| Progreso y analítica | Finalización manual o automática; tiempo y aperturas por recurso; paneles por rol; KPIs docentes | [[RF13]], [[RF18]], [[RF24]], [[RF25]], [[RF33]] |
| Gestión académica | Períodos, asignaturas, secciones, estadísticas de oferta | [[RF06]]–[[RF10]] |

## No incluye
| Exclusión | Motivo |
|---|---|
| Aplicación móvil | Un sistema web unificado y responsive |
| Contenido interactivo (H5P y plugins) | Integración compleja y propensa a errores, como pasa en Moodle |
| Mensajería interna o chat | Poco uso en Moodle y mucho desarrollo para poca utilidad |
| Mesa de ayuda dentro del sistema | No aprobada (P08 en [[Requisitos propuestos]]) |
| Medición de asistencia por API de Meet, cámara o evidencias ponderadas | [[ADR-003 Asistencia virtual por reporte de Meet]], [[ADR-012 Validación con cámara]] |
| Recuperación de contraseña propia | La gestiona Google ([[RF02]] descartado) |
| Editor de código, notebooks e IA | Se pidieron en entrevistas (E2, E5, E7), pero no están en el alcance |

## Posibles integraciones (fase posterior)
- **Calificación anónima** ([[ADR-006 Rúbricas y calificación anónima]]).
- Clonar un curso completo: ya lo cubre [[RF14]].
