---
tipo: adr
id: ADR-034
estado: aceptada
fecha: 2026-10-11
afecta: ["[[Layout base]]", "[[Vista de sección]]", "[[Mapa de vistas]]", "[[RNF05]]", "[[RF24]]"]
fuentes: []
---

# ADR-034 · Navegación y layouts

## Contexto
Los manuales describían rutas de menú sueltas («Mi perfil → Sesiones», «Notas → Acta»), pero no había un diseño común de la interfaz. Hay usuarios con varios roles (el Coordinador de ISI también es docente) y casi todos los roles trabajan sobre una misma sección. [[RNF05]] pide diseño mobile-first desde 360 px.

## Decisión
*Decidido por el equipo el 11/10/2026:*
1. **Cuatro layouts:** Invitado, Aplicación, Examen (cuestionarios, sin menú) y Proyección (QR en pantalla completa, sin nombres).
2. **Menú unificado por grupos:** un usuario con varios roles ve la suma de sus opciones agrupadas (Estudio, Docencia, Coordinación, Jefatura, Administración, Soporte, Consultas, Cuenta). No hay selector de «rol activo».
3. **Menú ☰ para todos los roles en el teléfono:** por debajo de 1024 px el menú lateral se abre con ☰; no hay barra inferior.
4. **Una sola Vista de sección** con pestañas por permiso (Contenidos, Actividades, Notas, Asistencia, Foro, Estudiantes, Analítica), en lugar de pantallas distintas por rol.

5. **Decisiones de detalle** (mismo día): datos personales en solo lectura; el Administrador mantiene el catálogo completo (asignaturas, departamentos, carreras, planes y aulas); paneles del Jefe y de Soporte; aviso de asistencia abierta; calendario solo en el panel; mensajes enviados en la sección; estado de Google Calendar en Mi perfil. Detalle en [[Layout base#Decisiones de detalle]].

## Opciones descartadas
- **Selector de rol activo:** más limpio, pero obliga a cambiar de modo para pasar de docente a coordinador.
- **Barra inferior en el teléfono** (para el estudiante o para todos): más rápida para el QR, pero dos patrones distintos de navegación. Se compensa con el aviso de «Asistencia abierta» en la cabecera.
- **Vista de curso distinta por rol:** más ajustada a cada uno, pero duplica pantallas y pruebas.

## Consecuencias
- Los menús se arman con `@can('<permiso>')` de spatie; ocultar una opción no reemplaza el middleware `permission:` ni el alcance del Service ([[ADR-026 Autorización sin Policies]]).
- La Vista de sección necesita pruebas por rol: qué pestañas y qué acciones aparecen para cada uno.
- El detalle está en `13 Layout/`: [[Layout base]], [[Vista de sección]], una nota por rol y el [[Mapa de vistas]] con ruta, componente, permiso y RF de cada vista.
- Los manuales usan los mismos nombres de menú.
