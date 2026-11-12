---
tipo: calidad
estado: en-curso
fecha: 2026-10-27
fuentes: ["Informe de Evaluación de Pruebas de QA con Evidencias - Rol Administrador LMS UES FMO (27/10/2026)", "Rol Administrador.docx (45 figuras)"]
rnf: ["[[RNF02]]", "[[RNF11]]", "[[RNF12]]", "[[RNF13]]"]
adr: ["[[ADR-052 Prerrequisitos exigidos al matricular]]", "[[ADR-053 Roles incompatibles con el de Estudiante]]", "[[ADR-054 Topes de la carga académica]]"]
---

# Respuesta al informe de QA · sesión 3 · rol Administrador

Tres personas probaron el rol Administrador el 26 y el 27 de octubre de 2026 sobre el despliegue de staging, en nueve módulos: **106 casos, 55 correctos, 21 parciales, 19 fallas y 11 pendientes**. Este documento clasifica cada hallazgo y dice qué se hizo con él. Los informes originales los conserva el equipo y no se versionan, porque traen capturas del staging.

> [!info] Cómo leer esto
> Un hallazgo puede ser un **defecto** (el sistema no hace lo que se decidió), una **función que falta** (un RF que sigue en desarrollo) o una **decisión de diseño** que la prueba leyó como defecto. Las tres cosas importan, pero se tratan distinto: el defecto se arregla, la función se planifica y la decisión se documenta o se cambia con una ADR.

## 1. Defectos corregidos en esta sesión

| Hallazgo | Módulo | Causa |
|---|---|---|
| **H-13** · No aparecen las preferencias de notificación | Mi perfil | La pestaña se ocultaba cuando la lista salía vacía, y los 19 eventos de `config/notificaciones.php` declaran audiencia **sin incluir `administrador`**, así que para ese rol se vaciaba siempre. Ahora la pestaña depende del permiso y explica cuándo no hay avisos configurables |
| **H-03** · Períodos con fechas traslapadas | Períodos | No había validación ni restricción. Se agregan las dos: la del Service y la declarativa |
| **H-08** · El catálogo no se audita | Catálogo | `subjects` y las tablas de la estructura institucional no estaban entre las que tienen trigger de auditoría |
| **H-06** · Fechas AAAA-MM-DD en «Estudiantes en riesgo» | Reportes | Fecha sin formatear ([[RNF11]]) |
| **H-07** · Envíos programados duplicados | Reportes | Faltaba el único y la validación ([[RNF12]]) |
| **H-10** · La búsqueda distingue las tildes | Catálogo | Comparación sin normalizar |
| **H-11** · Prerrequisitos solo con `Ctrl` | Catálogo | `<select multiple>`, inservible en pantallas táctiles |
| **P-01** · Editar un período solo al clicar su nombre | Períodos | Falta de acción visible |
| Figuras 1-2 · «Seleccionar archivo» no parece un botón | Importaciones | Input sin estilo ni `hover` |
| Figuras 7-9 · Materias sin filtrar por carrera | Matrícula | El desplegable ofrecía todas las de la facultad, y se logró inscribir a una estudiante de Administración en materias de Ingeniería |
| **N-08** · La ruta repite «Ayuda > Ayuda» | Ayuda | Miga duplicada en el registro de vistas |

Además, la matrícula ahora **rechaza** las asignaturas fuera del plan de la carrera aunque se fuerce la petición (CP-RF09-14), no solo las oculta del desplegable.

> [!warning] Pruebas pendientes
> Están automatizados CP-RF09-13, CP-RF09-14 y CP-RF23-05. Siguen en `pendiente` CP-RF04-22, CP-RF06-07, CP-RF06-08, CP-RF07-05 a CP-RF07-07, CP-RF08-06, CP-RF25-04 y CP-RF25-05: el código está, falta su prueba Pest.

## 2. Hallazgos que exigieron una decisión nueva

Tres hallazgos pedían reglas de negocio que el vault no definía. Se decidieron el 27/10/2026 y quedan como ADR **pendientes de aceptar**: el equipo las vota. Las tres ya están implementadas: ADR-052 en `MatriculaService`, ADR-053 en `JerarquiaService` y ADR-054 en `CargaAcademicaService`.

- **H-09 · Prerrequisitos exigidos al matricular** → [[ADR-052 Prerrequisitos exigidos al matricular]]. La prueba lo clasificó como «no tiene sentido», y con razón: el sistema pedía un dato que luego ignoraba. **Pero no era un defecto**: [[ADR-030 Matrícula ampliada]] y [[ADR-032 Ajustes por el documento de roles]] habían decidido expresamente no exigirlos, y la pantalla del catálogo lo declaraba. ADR-052 revierte esa decisión y la implementa. **Atención al riesgo que ADR-052 deja escrito:** el histórico académico real no está cargado, así que activar esto en producción sin el histórico bloquearía a todos los estudiantes.
- **BUG-01 · Estudiante junto con Docente o Soporte** → [[ADR-053 Roles incompatibles con el de Estudiante]]. Más grave de lo que parece: los alcances `M` (sus matrículas) y `S` (sus secciones) se resuelven por rol, así que esa cuenta veía la misma sección como estudiante y como docente, y podía calificarse a sí misma.
- **Topes de carga académica** (7 docentes en una sección, 17 horas semanales) → [[ADR-054 Topes de la carga académica]]. Los números, **2 docentes y 10 horas**, son provisionales y quedan en `system_settings`: los fija la Jefatura, no el equipo de desarrollo.

## 3. Hallazgos que no son defectos: son RF en desarrollo

El informe los marca como «falta», y es correcto, pero no se perdieron: están planificados.

| Hallazgo | Dónde vive |
|---|---|
| Reportes de progreso y participación (R-06, R-07) | [[RF18]] y [[RF25]], en desarrollo |
| Gráficos en los reportes (R-11) | [[RF25]]; la bitácora del 25/10 ya lo dejó anotado como pendiente |
| Filtros y selector de período en reportes (R-12) | [[RF25]] |
| Calendario de evaluaciones y sincronización (P-13) | [[RF06]] y [[RF21]], hito H3 |
| Exportar el histórico de períodos (P-14) | [[RF06]] |
| Egresados e histórico de la oferta (E-04, E-07) | [[RF10]], sin empezar |
| Fórmula oficial de proyección (E-08) | [[RF10]]; el sistema ya avisa que la fórmula es provisional |
| Cancelaciones por sección y revertirlas (X-01, X-06) | [[RF32]], en desarrollo |
| Manuales incompletos con notas de desarrollo (A-03, A-05) | ✅ 01/11: los 8 manuales redactados para el usuario final, sin notas internas; faltan las capturas |
| Duración de 20 semanas del ciclo (P-06) | Pendiente de decidir: el reglamento la fija, pero no está en ningún RF |
| Reabrir un período cerrado (P-10) | Pendiente de decidir: hoy el cierre es irreversible por diseño ([[RF34]]) |

## 4. Un hallazgo que no se sostiene y uno sin reproducir

- **H-12 · «No hay cierre remoto de sesiones»: la función existe.** El botón «Cerrar las demás» está en la pestaña «Sesiones» de Mi perfil y el Administrador tiene el permiso `perfil.sesiones.cerrar`. Lo que **no** existe es cerrar **una** sesión concreta, que es probablemente lo que la prueba buscaba. Queda como mejora, no como falla.
- **H-01 · Recuadro negro al exportar «Estudiantes en riesgo» a PDF.** ✅ **Reproducido y corregido el 01/11/2026.** El recuadro no estaba en el PDF: era el modal de error de Livewire tapando la pantalla. Con las 822 alertas de la demo, dompdf pedía 262 MB y el `memory_limit` de staging es 256 MB (`docker/php.ini`), así que la petición moría. Ahora el PDF lleva como máximo 500 filas (unas 23 páginas, 142 MB) con un aviso que manda al Excel o al CSV para la lista completa (`Exportacion::FILAS_PDF`), y el pie numera «Página X de Y» (antes «1 / 0»). Nota original: Las exportaciones entraron el 25/10 y la bitácora ya registra que Livewire exige `StreamedResponse` para una descarga, así que el staging probado puede ser anterior a esa corrección. **Primer paso: confirmar qué versión sirve Railway.**

## 5. Lo que la QA dejó pendiente y conviene cerrar

Once casos quedaron sin verificar, nueve de ellos en Clases canceladas, casi todos porque hacían falta otros roles. La Tabla 8 del informe propone las pruebas que los cerrarían. Dos avisos del propio informe que conviene atender:

- Durante las pruebas se crearon **dos cancelaciones de toda la facultad** (27/10 y 30/10/2026) que quedaron aprobadas y que nadie sabe revertir. Hay que limpiarlas en staging.
- El parámetro `group_change_weeks` aparece en el JSON de la auditoría pero no en ninguna pantalla. Es configurable en `system_settings`; falta exponerlo.
