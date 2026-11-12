---
tipo: layout
nombre: Guía de estilo para Stitch
estado: borrador
adr: ["[[ADR-034 Navegación y layouts]]", "[[ADR-016 Paquetes]]"]
rnf: ["[[RNF05]]"]
---

# Guía de estilo · LMS UES FMO (para Google Stitch)

> **Cómo usar este archivo:** cárguelo como contexto en Stitch y luego pida cada pantalla con los prompts de la sección 10. Todo lo que genere Stitch debe respetar esta guía: colores, tipografía, layout, menú por rol y textos en español de usted.

## 1. Producto
- **Qué es:** plataforma LMS (aula virtual) de **Ingeniería de Sistemas Informáticos** de la **Universidad de El Salvador, Facultad Multidisciplinaria de Oriente** (San Miguel).
- **Usuarios:** 6 roles. Estudiante, Docente, Coordinador, Jefe de Departamento, Administrador y Soporte Técnico. Un usuario puede tener **varios roles** (p. ej., Coordinador y Docente).
- **Dispositivos:** **mobile-first**. El estudiante usa el teléfono (sobre todo para la asistencia con QR) y el personal usa la computadora. Debe verse bien desde **360 px**.
- **Idioma:** español de El Salvador, tratando al usuario de **usted** («Inscríbase», «Revise su boleta»). Fechas `DD/MM/AAAA`, horas de 24 h (`07:00`), decimales con punto (`7.5`).
- **Tono visual:** institucional, sobrio y claro. Es una herramienta de trabajo diario: debe tener mucho espacio en blanco, jerarquía clara, pocos adornos y nada de ilustraciones infantiles.

## 2. Paleta de colores (UES)
Los colores salen del sitio oficial [ues.edu.sv](https://www.ues.edu.sv). El **rojo UES** es el color de la marca.

### Marca
| Token | Hex | Uso |
|---|---|---|
| `marca-50` | `#FDF2F3` | Fondo de la opción activa del menú, fondos suaves |
| `marca-100` | `#FBE4E6` | Insignias de marca, hover suave |
| `marca-200` | `#F6C6CC` | Bordes de elementos de marca |
| `marca-300` | `#EE98A3` | Decorativo (gráficos) |
| `marca-400` | `#E05F72` | Gráficos |
| `marca-500` | `#CB3149` | Gráficos, foco |
| **`marca-600`** | **`#AE152D`** | **Rojo UES oficial**: botón primario, enlaces, logo, opción activa |
| **`marca-700`** | **`#8B0E13`** | **Rojo UES oscuro oficial**: hover del botón primario, títulos de marca |
| `marca-800` | `#6E0B10` | Texto sobre `marca-50` |
| `marca-900` | `#520A0D` | Fondos oscuros (layout de proyección) |
| `acento` | `#9D2235` | Vino institucional: detalles, separadores, gráficos |

### Neutros
> El fondo y el borde se oscurecieron un punto el 25/10/2026. Con `#F8F8F8` y `#E2E2E2`, una tarjeta blanca de 1 px de borde no se distinguía del lienzo y las pantallas se leían como recuadros blancos sueltos.

| Token | Hex | Uso |
|---|---|---|
| `fondo` | `#F1F1F2` | Fondo de la aplicación |
| `superficie` | `#FFFFFF` | Tarjetas, tablas, menú, cabecera |
| `borde` | `#DCDCDE` | Bordes y divisores |
| `texto-suave` | `#696969` | Texto secundario, etiquetas |
| `texto-desactivado` | `#ABABAB` | Opciones «Pronto», placeholders |
| `texto` | `#1A171B` | Texto principal y títulos |

### Estados (siempre con ícono y texto, nunca solo color)
| Estado | Texto | Fondo | Uso |
|---|---|---|---|
| Éxito | `#15803D` | `#F0FDF4` | Registrado, entregada, aprobado, validada |
| Aviso | `#B45309` | `#FFFBEB` | Asistencia cerca del límite (< 85 %), tardía, pendiente |
| Error | `#B91C1C` | `#FEF2F2` | Rechazo, reprobado, error de validación, < 75 % |
| Información | `#1D4ED8` | `#EFF6FF` | Avisos neutros, ayudas |

> [!warning] Rojo de marca y rojo de error
> Los dos son rojos. Por eso los errores **siempre** llevan fondo claro, ícono de advertencia y texto explícito. Las acciones destructivas (Retirar, Revocar, Eliminar) usan un **botón contorneado** en rojo de error, no el rojo de marca relleno.

### Contraste (WCAG 2.1 AA)
- Texto blanco sobre `#AE152D` y `#8B0E13`: cumple AA.
- No ponga texto `#ABABAB` sobre blanco para contenido importante: solo para lo deshabilitado.

## 3. Tipografía
- **Fuente:** **Instrument Sans** (Google Fonts), para todo. El sitio de la UES usa Gordita y Butler, que son comerciales; Instrument Sans es la alternativa libre más cercana y ya está en el código.
- **Escala:**

| Uso | Tamaño | Peso |
|---|---|---|
| Título de página (h1) | 24 px | 600 |
| Título de sección o tarjeta (h2) | 16–18 px | 600 |
| Texto | 14–16 px | 400 |
| Etiquetas, tablas, insignias | 12–14 px | 500 |
| Título del grupo del menú | 12 px, MAYÚSCULAS, espaciado | 600, `#696969` |
| QR proyectado: título | 36 px | 600 |

## 4. Forma, espacio y elevación
- **Radios:** 8 px en botones e inputs; 12 px en tarjetas, tablas y menús desplegables; 16 px en modales; círculo en avatares e insignias.
- **Espaciado:** múltiplos de 4 px. 16 px de margen lateral en el teléfono y 24–32 px en la computadora. Separación entre tarjetas de 12–16 px.
- **Sombras:** mínimas. Tarjetas con borde de 1 px `#E2E2E2` y una sombra muy suave; los menús desplegables y modales, con sombra media.
- **Íconos:** **Heroicons**, estilo contorno, 20–24 px, trazo de 1.5.
- **Logo:** use un cuadrado redondeado rojo `#AE152D` con «UES» en blanco como **marcador**. El escudo oficial se coloca después; **no lo dibuje ni lo invente**.

## 5. Layouts
### 5.1 Layout Aplicación (todas las pantallas con sesión)
```
┌──────────────────────────────────────────────────────────────────┐
│ ☰ [UES] LMS UES FMO     Período 2026-II · en curso   ?  🔔3  (KR)▾│ cabecera 56 px, blanca
├───────────────┬──────────────────────────────────────────────────┤
│ Inicio        │ Inicio › Mis materias › PRN115 · Grupo 01        │ migas de pan
│ ESTUDIO       │ Título de la página                [Acción]      │ h1 + botón primario
│ Escanear QR   │ ┌──────────────────────────────────────────────┐ │
│ Mis materias  │ │ contenido (tarjetas, tablas, formularios)    │ │
│ Mis notas     │ └──────────────────────────────────────────────┘ │
│ CUENTA        │                                                  │
│ Mi perfil     │                                  [aviso flotante]│
│ Cerrar sesión │                                                  │
└───────────────┴──────────────────────────────────────────────────┘
 menú 256 px (contraído: 64 px, solo íconos)        fondo #F1F1F2
```
- **Cabecera:**
  - ☰ abre el menú.
  - Logo y «LMS UES FMO».
  - Insignia del período activo («Período 2026-II · en curso»).
  - **?** abre la ayuda de la pantalla.
  - Campana con contador rojo.
  - Avatar con iniciales y un menú: nombre, correo, Mi perfil, Ayuda, Cerrar sesión.
- **Menú lateral:**
  - Fijo desde 1024 px. En el teléfono y la tableta se abre con **☰** como panel deslizante desde la izquierda, con fondo oscurecido.
  - La opción activa lleva fondo `#FDF2F3`, texto `#8B0E13` e ícono `#AE152D`.
  - Las opciones aún no disponibles van en gris `#ABABAB` con la etiqueta «PRONTO».
- **Franja de emergencia:** si entra con la cuenta de emergencia, aparece una franja roja fija arriba: «Está usando la cuenta de emergencia».
- **Aviso de asistencia abierta (estudiante):** una franja en la cabecera, «Asistencia abierta · PRN115 · Marcar», que lleva a Escanear QR.

### 5.2 Layout Invitado
Fondo `#F8F8F8`, logo centrado, el texto «Universidad de El Salvador · Facultad Multidisciplinaria de Oriente» y una **tarjeta blanca centrada** de 448 px de ancho. Lo usan el inicio de sesión y las páginas de error (403, 404, 419 «Su sesión expiró», 500).

### 5.3 Layout Examen
**Sin menú ni campana.**
- Cabecera fija con:
  - el título del cuestionario;
  - el **temporizador** (⏱ 42:15), que se pone rojo en el último minuto;
  - el estado del autoguardado («✓ Guardado» / «Guardando…» / «Sin conexión»).
- Una pregunta por pantalla.
- Pie fijo con Anterior, los números de las preguntas (la actual resaltada en rojo UES) y Siguiente, más el botón «Terminar intento».

### 5.4 Layout Proyección (QR en el aula)
Pantalla completa **oscura** (`#1A171B` o `#520A0D`):
- título grande («PRN115 · Grupo 01 · Asistencia»);
- **QR enorme en un recuadro blanco** en el centro;
- debajo, «Cierra en 12:41» y «Registrados: 27 / 38».

**Nunca muestra nombres de estudiantes.**

## 6. Menú por rol
Un usuario con varios roles ve **la suma** de sus grupos, en este orden. Cada opción aparece una sola vez.

| Grupo | Rol | Opciones (ícono Heroicons) |
|---|---|---|
| — | Todos | Inicio (`home`) |
| **ESTUDIO** | Estudiante | Escanear QR (`qr-code`) · Mis materias (`book-open`) · Mis notas (`chart-bar`) · Mi asistencia (`clipboard-document-check`) |
| **DOCENCIA** | Docente | Mis secciones (`academic-cap`) · Pendientes de calificar (`inbox-stack`, contador) · Bancos de preguntas (`rectangle-stack`) |
| **COORDINACIÓN** | Coordinador | Supervisión de aulas (`eye`) · Carga académica (`building-office`) · Matrícula (`user-plus`, contador) · Importar estudiantes (`arrow-up-tray`) · Actas por validar (`clipboard-document-list`, contador) · Clases canceladas (`x-circle`) |
| **JEFATURA** | Jefe | Secciones del departamento (`building-library`) · Cierre del período (`lock-closed`) · Desempeño docente (`chart-pie`) |
| **ADMINISTRACIÓN** | Administrador | Usuarios (`users`) · Roles y permisos (`shield-check`) · Períodos (`calendar-days`) · Catálogo (`squares-2x2`) · Carga académica · Matrícula · Áreas de asistencia (`map-pin`) · Clases canceladas · Integraciones (`puzzle-piece`) · Colas (`queue-list`) |
| **SOPORTE** | Soporte | Solicitudes de dispositivo (`device-phone-mobile`, contador) · Diagnóstico de asistencia (`signal`) · Usuarios (consulta) |
| **CONSULTAS** | Según permiso | Reportes (`document-chart-bar`) · Estadísticas de oferta (`presentation-chart-line`) · Auditoría (`finger-print`) |
| **CUENTA** | Todos | Mi perfil (`user-circle`) · Ayuda (`question-mark-circle`) · Cerrar sesión |

## 7. Componentes
| Componente | Estilo |
|---|---|
| **Botón primario** | Fondo `#AE152D`, texto blanco, hover `#8B0E13`, radio 8 px, 14 px, peso 500; ícono opcional a la izquierda |
| **Botón secundario** | Fondo blanco, borde `#E2E2E2`, texto `#1A171B` |
| **Botón destructivo** | Contorno rojo de error `#B91C1C`; al confirmar en el modal, relleno de error |
| **Botón fantasma** | Sin fondo; hover gris claro |
| **Tarjeta** | Blanca, borde 1 px, radio 12 px; cabecera con título y acciones a la derecha |
| **Tabla** | Cabecera gris claro en MAYÚSCULAS de 12 px; filas con divisor; en el teléfono se convierte en **tarjetas apiladas** |
| **Insignia** | Píldora pequeña; colores: gris, marca, verde, ámbar, rojo y azul |
| **Campo de formulario** | Etiqueta arriba (`*` rojo si es obligatorio), input con borde, ayuda gris debajo y error en rojo con ícono |
| **Modal** | Centrado en la computadora y como **hoja inferior** en el teléfono; título, cuerpo y pie con Cancelar (secundario) y la acción (primario) a la derecha |
| **Acordeón** | Unidades del curso y bloques del panel; chevron que gira |
| **Pestañas** | Subrayado rojo UES en la activa; en el teléfono se desplazan en horizontal |
| **Aviso flotante** | Abajo a la derecha, blanco con borde de color según el estado y botón ✕ |
| **Estado vacío** | Ícono en un círculo gris, título y una frase que explica **qué hacer**, más un botón |
| **Barra de avance** | Delgada (6 px), relleno rojo UES y el porcentaje a la derecha |
| **Migas de pan** | Gris con separador `›`; la última, en negro |

## 8. Pantallas clave
Las 68 pantallas están en [[Mapa de vistas]]. Estas definen el estilo y conviene generarlas primero:

| # | Pantalla | Rol | Layout | Contenido principal |
|---|---|---|---|---|
| 1 | Iniciar sesión | Todos | Invitado | Botón «Iniciar sesión con Google» y nota «Use su cuenta @ues.edu.sv» |
| 2 | Inicio | Estudiante | Aplicación | Tarjeta «Marcar asistencia» si hay una abierta; pendientes por fecha; calendario de la semana; tarjetas de materias con avance; asistencia con alertas |
| 3 | Escanear QR | Estudiante | Aplicación (teléfono) | Visor de cámara a pantalla completa, intentos restantes (3), resultado grande (✓ Registrado / ✗ motivo) y el enlace «Ingresar código temporal» |
| 4 | Mis materias | Estudiante | Aplicación | Tarjetas por materia (código, nombre, grupo, docente, avance) y el botón «Inscribirme con clave» |
| 5 | Vista de sección · Contenidos | Estudiante y Docente | Aplicación | Cabecera del curso, pestañas, unidades en acordeón con sus recursos (PDF, video, página, carpeta, enlace) |
| 6 | Intento de cuestionario | Estudiante | Examen | Pregunta de opción múltiple, temporizador y navegación |
| 7 | Mis notas | Estudiante | Aplicación | Boleta por materia y cómputo, con pesos, y la vista por unidad |
| 8 | Inicio | Docente | Aplicación | Clases de hoy con «Abrir asistencia», pendientes de calificar y alertas de estudiantes |
| 9 | Sesión de asistencia | Docente | Aplicación | Contadores (presentes, tardes, ausentes), «Proyectar QR», «Código temporal», lista de estudiantes con su estado y rechazos con motivo |
| 10 | Proyectar QR | Docente | Proyección | QR grande, cuenta regresiva y registrados |
| 11 | Libro de calificaciones | Docente | Aplicación | Tabla estudiantes × actividades por cómputo, promedio y «Publicar notas» |
| 12 | Carga académica | Coordinador | Aplicación | Secciones por asignatura con grupo, cupo, docente y horario; asignar docente |
| 13 | Actas por validar | Coordinador | Bandeja | Lista de actas publicadas; Validar o Devolver con observaciones |
| 14 | Cierre del período | Jefe | Aplicación | Avance de las actas por carrera y «Autorizar cierre» |
| 15 | Usuarios | Administrador | Aplicación | Tabla con filtros, «Nuevo usuario», «Importar Excel» |
| 16 | Solicitudes de dispositivo | Soporte | Bandeja | Solicitud con el motivo y el historial de teléfonos; Aprobar o Rechazar |
| 17 | Mi perfil | Todos | Aplicación | Pestañas: Datos personales (solo lectura), Dispositivo, Notificaciones, Sesiones |

## 9. Datos de ejemplo (úselos en los diseños)
- **Período:** 2026-II (27/07/2026 – 11/12/2026), en curso.
- **Materias:**
  - PRN115 Programación I
  - BAD115 Bases de Datos I
  - DSI115 Diseño de Sistemas I
  - SIO115 Sistemas Operativos
  - ANS115 Análisis Numérico
- **Grupos:** 01 (mañana) y 02 (tarde).
- **Aulas:** A-101 … B-205, LAB-1.
- **Horario:** Lun y Mié 07:00–08:40.
- **Carné y correo:** `gs21004@ues.edu.sv`. **Nombres:** Ana Ruiz, Carlos Martínez, Gabriela Sánchez.
- **Notas:** escala de 0 a 10 con un decimal (7.5); se aprueba con **6.0**.
- **Cómputos:** 30 % · 30 % · 40 %.
- **Asistencia:** 92 % (verde), 82 % (ámbar, aviso preventivo bajo 85 %), 74 % (rojo, por debajo del mínimo de 75 %).

## 10. Prompts listos para Stitch
Pegue primero este bloque de contexto. Después pida una pantalla por mensaje.

**Contexto (una vez):**
> Diseña pantallas para «LMS UES FMO», el aula virtual de la Universidad de El Salvador, Facultad Multidisciplinaria de Oriente. Sigue estrictamente la guía de estilo adjunta:
> - Rojo UES `#AE152D` como color primario y `#8B0E13` para el hover. Fondo `#F8F8F8`, tarjetas blancas con borde `#E2E2E2` y radio de 12 px.
> - Tipografía Instrument Sans e íconos Heroicons de contorno.
> - Interfaz en español, tratando al usuario de usted.
> - Diseño sobrio e institucional, mobile-first desde 360 px, con contraste WCAG AA.
> - Usa el Layout Aplicación: cabecera blanca de 56 px con ☰, logo «UES» en un cuadrado rojo, período activo, ayuda, campana y avatar; menú lateral de 256 px agrupado por rol.

**Ejemplos de pantalla:**
1. > Pantalla «Inicio» del estudiante, en móvil (390 px) y escritorio (1440 px). Arriba, una tarjeta destacada «Asistencia abierta · PRN115 · Marcar asistencia». Luego, «Pendientes» ordenados por fecha (Tarea 2 de BAD115 vence el 16/10, Parcial 2 de PRN115 el 20/10), un calendario semanal, tarjetas de 5 materias con barra de avance roja y un bloque «Mi asistencia» con 92 % en verde y 82 % en ámbar.
2. > Pantalla «Escanear QR» del estudiante, solo en móvil. Visor de cámara con un marco de escaneo, el texto «Apunte al QR que proyecta su docente», «Intentos restantes: 3» y el enlace «Ingresar código temporal». Diseña también los dos resultados: «✓ Asistencia registrada · 07:06» en verde y «✗ Fuera del área del campus (a 640 m)» en rojo, con «Reintentar».
3. > Pantalla «Sesión de asistencia» del docente, en escritorio. Cabecera del curso PRN115 · Grupo 01 · Lun 07:00–08:40 · A-101. Contadores: Presentes 27, Tardes 3, Ausentes 8. Botones «Proyectar QR» (primario), «Código temporal» y «Cerrar sesión de asistencia». Tabla de estudiantes con su estado (insignias verde, ámbar y gris), método (QR, código, manual) y una sección «Rechazos» con su motivo.
4. > Layout Proyección: fondo `#1A171B`, título blanco «PRN115 · Grupo 01 · Asistencia», un QR grande en un recuadro blanco, «Cierra en 12:41» y «Registrados: 27 / 38». Sin nombres de estudiantes.
5. > Menú del Layout Aplicación para un usuario que es **Coordinador y Docente**: grupos DOCENCIA (Mis secciones, Pendientes de calificar con contador 12, Bancos de preguntas, Rúbricas), COORDINACIÓN (Supervisión de aulas, Carga académica, Matrícula con contador 3, Importar estudiantes, Actas por validar con contador 5, Clases canceladas), CONSULTAS (Reportes, Estadísticas de oferta) y CUENTA. Muestra la versión de escritorio, la contraída a íconos y el panel móvil abierto con ☰.

## 11. Reglas para no salirse del diseño
- **Sí:** jerarquía clara, una acción primaria por pantalla, estados vacíos útiles, confirmación con **motivo** en las acciones que se auditan (retirar, corregir nota, revocar), carga con *skeleton*.
- **No:**
  - modo oscuro (salvo la proyección);
  - degradados ni ilustraciones decorativas;
  - más de un botón rojo relleno por vista;
  - texto en inglés;
  - inventar el escudo de la UES;
  - mostrar coordenadas GPS (solo la distancia);
  - mostrar nombres de estudiantes en la proyección;
  - mostrar el nombre del estudiante en las evaluaciones de calificación anónima («Estudiante 1, 2…»).
- Lo que se diseñe en Stitch se implementa con los componentes `x-ui.*` y los layouts de [[Layout base#Implementación]].
