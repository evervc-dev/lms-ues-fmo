---
tipo: layout
nombre: Maquetas
estado: borrador
adr: ["[[ADR-034 Navegación y layouts]]"]
---

# Maquetas de interfaz (Stitch)

Pantallas generadas con Google Stitch a partir de la [[Guía de estilo para Stitch]] e importadas el 11/10/2026 (tres entregas). Hay **52 maquetas**: la imagen está en `Maquetas/` y su HTML de referencia en `Maquetas/html/`. El sistema de diseño que Stitch extrajo está en [[Sistema de diseño (Stitch)]].

> [!info] Cómo se usan
> - Cada maqueta es **la referencia visual** de la tarea `RFxx · Front` de su requisito.
> - Se construye con los layouts y los componentes `x-ui.*` del proyecto ([[Layout base#Implementación]]), **no copiando el HTML**: Stitch usa Tailwind por CDN, otra fuente y otros íconos.
> - El contenido y los permisos siguen mandando según el vault: [[Mapa de vistas]], RF y [[Matriz RBAC]]. Si la maqueta contradice al vault, gana el vault.

## Correcciones al construirlas
Stitch respetó la paleta UES, el español de usted y la regla de no mostrar nombres en la proyección. Estas son sus desviaciones:

| # | Desviación | Corrección |
|---|---|---|
| 1 | Fuente **Hanken Grotesk** | Usar **Instrument Sans** (la del proyecto) |
| 2 | Íconos **Material Symbols** | Usar **Heroicons** con `x-ui.icon`; buscar el equivalente |
| 3 | Colores extra de Material (verde azulado `#004852`, superficies rosadas `#FFF7FD`) | Usar solo los tokens de la guía: marca, neutros y estados |
| 4 | El usuario «Docente y Coordinador» (17, 23) ve los grupos **ESTUDIO** y **JEFATURA** | Mostrar solo los grupos de sus roles, como hace `App\Support\Navegacion\Menu` |
| 5 | El aviso «Asistencia abierta · Marcar» aparece a un docente (17, 23) | Mostrarlo solo a estudiantes con una sesión abierta |
| 6 | Códigos internos a la vista: «Tolerancia RF20», «Motor RF08», «(RF28)» | Ningún código de requisito en la interfaz |
| 7 | Datos de ejemplo inexactos (PRN115 como «Ciclo II», «Radio: 80 m») | Usar los datos de la demo: PRN115 es del ciclo 1 y el radio del campus es de 450 m |
| 8 | En 45 (Inicio administrador), las tarjetas de fases del período se superponen a la tabla de departamentos (error de Stitch) | Las fases van en una fila propia, encima de la tabla |
| 9 | En 34 (Áreas de asistencia), el usuario es «Docente y Coordinador» | Es una pantalla del **Administrador**: su menú solo muestra Administración, Consultas y Cuenta |
| 10 | En 46 (Inicio soporte), el menú muestra el grupo **CONSULTAS** | Soporte no tiene permisos de reportes, oferta ni auditoría: su menú es solo Soporte y Cuenta |

## Cobertura por requisito
| # | Maqueta | Vista del [[Mapa de vistas]] | Rol | RF | Tarea Front |
|---|---|---|---|---|---|
| 01 | Iniciar sesión | Iniciar sesión | Todos | [[RF01]] | OP#379 |
| 02 | Inicio estudiante | Inicio (panel) | Estudiante | [[RF24]] | OP#467 |
| 03 | Inicio estudiante (móvil) | Inicio (panel) | Estudiante | [[RF24]] | OP#467 |
| 04 | Escanear QR (móvil) | Escanear QR | Estudiante | [[RF20]], [[RF28]] | OP#451, OP#491 |
| 05 | Mis materias | Mis materias + Inscribirme con clave | Estudiante | [[RF18]], [[RF29]] | OP#443, OP#495 |
| 06 | Vista de sección · Contenidos | Contenidos | Estudiante y Docente | [[RF11]] | OP#415 |
| 07 | Vista de sección (móvil) | Contenidos | Estudiante | [[RF11]] | OP#415 |
| 08 | Tarea · detalle y entrega | Tarea | Estudiante | [[RF15]] | OP#431 |
| 09 | Tarea · detalle y entrega (móvil) | Tarea | Estudiante | [[RF15]] | OP#431 |
| 10 | Layout Examen · intento | Intento de cuestionario | Estudiante | [[RF16]] | OP#435 |
| 11 | Mis notas | Mis notas | Estudiante | [[RF17]] | OP#439 |
| 12 | Mis notas (móvil) | Mis notas | Estudiante | [[RF17]] | OP#439 |
| 13 | Mi asistencia | Mi asistencia | Estudiante | [[RF22]] | OP#459 |
| 14 | Mi asistencia (variante) | Mi asistencia | Estudiante | [[RF22]] | OP#459 |
| 15 | Modal · Justificar inasistencia | Mi asistencia | Estudiante | [[RF19]], [[RF22]] | OP#447, OP#459 |
| 16 | Mi perfil | Mi perfil (pestañas) | Todos | [[RF01]], [[RF23]], [[RF27]] | OP#379, OP#463, OP#487 |
| 17 | Inicio docente y coordinador | Inicio (panel) | Docente y Coordinador | [[RF24]] | OP#467 |
| 18 | Pendientes de calificar | Pendientes de calificar | Docente | [[RF15]], [[RF16]] | OP#431, OP#435 |
| 19 | Crear o editar tarea | Crear o editar tarea | Docente | [[RF15]] | OP#431 |
| 20 | Libro de calificaciones | Notas (libro) | Docente | [[RF17]] | OP#439 |
| 21 | Sesión de asistencia | Sesión de asistencia | Docente | [[RF19]], [[RF20]], [[RF28]] | OP#447, OP#451, OP#491 |
| 22 | Layout Proyección · QR | Proyectar QR | Docente | [[RF20]] | OP#451 |
| 23 | Carga académica | Carga académica | Coordinador | [[RF08]] | OP#403 |
| 24 | Carga académica · resolver choque | Carga académica | Coordinador | [[RF08]] | OP#403 |
| 25 | Actas por validar | Actas por validar | Coordinador | [[RF34]] | OP#515 |
| 26 | Cierre del período | Cierre del período | Jefe | [[RF34]] | OP#515 |
| 27 | Desempeño docente | Desempeño docente | Jefe | [[RF33]] | OP#511 |
| 28 | Auditoría departamental | Auditoría | Jefe | [[RF05]] | OP#391 |
| 29 | Usuarios | Usuarios | Administrador | [[RF04]] | OP#387 |
| 30 | Solicitudes de dispositivo | Solicitudes de dispositivo | Soporte | [[RF27]] | OP#487 |
| 31 | Diagnóstico de asistencia | Diagnóstico de asistencia | Soporte | [[RF20]] | OP#451 |
| 32 | Banco de preguntas | Banco (preguntas) | Docente | [[RF16]] | OP#435 |
| 33 | Cuestionario · detalle | Cuestionario | Docente y Estudiante | [[RF16]] | OP#435 |
| 34 | Áreas de asistencia | Áreas de asistencia | Administrador | [[RF26]] | OP#483 |
| 35 | Matrícula | Matrícula · Estudiantes | Coordinador | [[RF09]] | OP#407 |
| 36 | Claves por asignatura | Matrícula · Claves | Coordinador | [[RF29]] | OP#495 |
| 37 | Solicitudes de cambio de grupo | Matrícula · Solicitudes | Coordinador | [[RF09]] | OP#407 |
| 38 | Estudiantes de la sección | Estudiantes de la sección | Docente, Coordinador y Jefe | [[RF09]] | OP#407 |
| 39 | Importar de un período anterior | Importar de un período anterior | Docente | [[RF14]] | OP#427 |
| 40 | Foro | Foro | Estudiante y Docente | [[RF30]] | OP#499 |
| 41 | Hilo del foro | Hilo del foro | Estudiante y Docente | [[RF30]] | OP#499 |
| 42 | Analítica de la sección | Analítica | Docente y Coordinador | [[RF13]] | OP#423 |
| 43 | Clases canceladas | Clases canceladas | Coordinador y Administrador | [[RF32]] | OP#507 |
| 44 | Inicio jefatura | Inicio (panel) | Jefe | [[RF24]] | OP#467 |
| 45 | Inicio administrador | Inicio (panel) | Administrador | [[RF24]] | OP#467 |
| 46 | Inicio soporte | Inicio (panel) | Soporte | [[RF24]] | OP#467 |
| 47 | Reportes | Reportes | Docente, Coordinador, Jefe y Administrador | [[RF25]] | OP#475 |
| 48 | Acta de la sección | Acta | Docente | [[RF34]] | OP#515 |
| 49 | Ayuda · manual del docente | Ayuda | Todos | — | — |
| 50 | Estadísticas de oferta | Estadísticas de oferta | Coordinador, Jefe y Administrador | [[RF10]] | OP#411 |
| 51 | Integraciones | Integraciones | Administrador | [[RNF09]] | — |
| 52 | Mi perfil · Google Calendar | Mi perfil › Google Calendar | Docente | [[RF21]] | OP#455 |

**Con maqueta (28 de 33 RF):** todos salvo RF03 (roles y permisos), RF06 (períodos), RF07 (catálogo), RF12 (ver recurso con video) y RF31 (mensajear a estudiantes que…). El registro de vistas (`config/vistas.php`) tiene maqueta en **42 de 60** rutas.

**Pantallas que faltan (18)**, todas de H0–H1 y en una sola tanda de Stitch (hasta 16 por exportación):

| Hito | Pantalla | Ruta | Equipo |
|---|---|---|---|
| H0 | Roles y permisos | `/roles` | E1 |
| H1 | Importar estudiantes (subir Excel y vista previa) | `/usuarios/importar` | E1 |
| H1 | Lote de importación (creados, duplicados y errores) | `/usuarios/lotes/{lote}` | E1 |
| H1 | Detalle de usuario | `/usuarios/{usuario}` | E1 |
| H1 | Listado de secciones (Mis secciones / Supervisión de aulas) | `/secciones` | E2 |
| H1 | Períodos | `/periodos` | E2 |
| H1 | Detalle del período (etapas, cómputos y cierres) | `/periodos/{periodo}` | E2 |
| H1 | Catálogo de asignaturas | `/catalogo` | E2 |
| H1 | Departamentos, carreras, planes y aulas | `/catalogo/estructura` | E2 |
| H1 | Notificaciones | `/notificaciones` | E3 |
| H1 | Ver recurso (PDF, video y página) | `/secciones/{s}/recursos/{r}` | E3 |
| H1 | Papelera de la sección | `/secciones/{s}/papelera` | E3 |
| H1 | Pestaña Actividades (tareas y cuestionarios por cómputo) | `/secciones/{s}/actividades` | E4 |
| H1 | Entregas de una tarea (calificar, anónima) | `/secciones/{s}/tareas/{t}/entregas` | E4 |
| H1 | Nuevo cuestionario (reglas, tiempo y navegación) | `/secciones/{s}/cuestionarios/crear` | E4 |
| H1 | Bancos de preguntas (listado) | `/bancos-de-preguntas` | E4 |
| H1 | ~~Rúbricas~~ (quitada: la rúbrica se arma en la tarea, [[ADR-065 La rúbrica es parte de la tarea]]) | ~~`/rubricas`~~ | E4 |
| H1 | Pestaña Asistencia (sesiones del docente) | `/secciones/{s}/asistencia` | E5 |

También falta el modal **Mensajear a estudiantes que…** ([[RF31]]), que va dentro de *20 Libro de calificaciones*.

## Galería
### Estudiante
![[01 Iniciar sesión.png|420]] ![[02 Inicio estudiante.png|420]] ![[03 Inicio estudiante (móvil).png|200]] ![[04 Escanear QR (móvil).png|240]]
![[05 Mis materias.png|420]] ![[06 Vista de sección · Contenidos.png|420]] ![[07 Vista de sección (móvil).png|240]]
![[08 Tarea · detalle y entrega.png|420]] ![[09 Tarea · detalle y entrega (móvil).png|240]] ![[10 Layout Examen · intento.png|420]]
![[11 Mis notas.png|420]] ![[12 Mis notas (móvil).png|240]] ![[13 Mi asistencia.png|420]] ![[14 Mi asistencia (variante).png|420]]
![[15 Modal · Justificar inasistencia.png|420]] ![[16 Mi perfil.png|420]]

### Docente
![[17 Inicio docente y coordinador.png|420]] ![[18 Pendientes de calificar.png|420]] ![[19 Crear o editar tarea.png|420]]
![[20 Libro de calificaciones.png|420]] ![[21 Sesión de asistencia.png|420]] ![[22 Layout Proyección · QR.png|420]]

### Coordinador, Jefe, Administrador y Soporte
![[23 Carga académica.png|420]] ![[24 Carga académica · resolver choque.png|420]] ![[25 Actas por validar.png|420]]
![[26 Cierre del período.png|420]] ![[27 Desempeño docente.png|420]] ![[28 Auditoría departamental.png|420]]
![[29 Usuarios.png|420]] ![[30 Solicitudes de dispositivo.png|420]] ![[31 Diagnóstico de asistencia.png|420]]

### Segunda entrega (32–45)
![[32 Banco de preguntas.png|420]] ![[33 Cuestionario · detalle.png|420]] ![[34 Áreas de asistencia.png|420]]
![[35 Matrícula.png|420]] ![[36 Claves por asignatura.png|420]] ![[37 Solicitudes de cambio de grupo.png|420]]
![[38 Estudiantes de la sección.png|420]] ![[39 Importar de un período anterior.png|420]] ![[40 Foro.png|420]]
![[41 Hilo del foro.png|420]] ![[42 Analítica de la sección.png|420]] ![[43 Clases canceladas.png|420]]
![[44 Inicio jefatura.png|420]] ![[45 Inicio administrador.png|420]]

### Tercera entrega (46–52)
![[46 Inicio soporte.png|420]] ![[47 Reportes.png|420]] ![[48 Acta de la sección.png|420]]
![[49 Ayuda · manual del docente.png|420]] ![[50 Estadísticas de oferta.png|420]] ![[51 Integraciones.png|420]] ![[52 Mi perfil · Google Calendar.png|420]]
