---
tipo: calidad
estado: en-curso
fecha: 2026-10-30
fuentes: ["Rol Administrador (versión del 30/10/2026, 82 páginas): re-pruebas de la sesión 3 y sección «Pruebas LMS-UES · Usuarios, Roles y permisos, Auditoría»"]
rnf: ["[[RNF01]]", "[[RNF02]]"]
adr: ["[[ADR-060 Formato de los nombres de usuario]]"]
---

# Respuesta al informe de QA · sesión 4 · rol Administrador

La QA entregó el 30/10/2026 una versión nueva del informe del rol Administrador. Trae dos cosas: las **re-pruebas** de los hallazgos de la [[Respuesta al informe de QA (sesión 3, rol Administrador)|sesión 3]] y una **sección nueva** con pruebas de Usuarios, Roles y permisos, Auditoría, Colas, Integraciones, Áreas de asistencia y la proyección del QR. Como en la sesión 3, el informe no se versiona porque trae capturas del staging.

> [!info] Cómo leer esto
> Cada hallazgo es un **defecto** (se arregla), una **decisión de diseño** que la prueba leyó como defecto (se documenta o se cambia con una ADR) o algo **por confirmar** en staging.

## 1. Re-pruebas de la sesión 3

| Hallazgo | Estado en la re-prueba | Comentario |
|---|---|---|
| **H-03** · Períodos con fechas cruzadas | Corregido | Prueba Pest en el PR #73 (CP-RF06-07) |
| Auditoría de `period.created` | Corregido | — |
| **C-08** · Baja lógica en el catálogo | Correcto | — |
| **H-10** · Búsqueda sin tildes | Correcto | — |
| **H-08** · El catálogo no se audita | **Parcial**: «parece que se duplica la auditoría de creación y de prerrequisitos» | Era un defecto real; se corrigió en esta sesión (sección 2) |

## 2. Defectos corregidos en esta sesión (PR #74)

| Hallazgo | Módulo | Causa | Prueba |
|---|---|---|---|
| Auditoría duplicada del catálogo (H-08 parcial) | Catálogo | `CatalogoService::guardarAsignatura()` hacía `insert` y luego `update` de la misma fila al crear, y en cada guardado borraba y reinsertaba todos los prerrequisitos. Ahora solo escribe lo que cambia | CP-RF07-06 (la de auditoría corre en PostgreSQL y falla con el código anterior) |
| «Correo repetido: falta una alerta» | Usuarios | El Service rechazaba bien, pero el error caía en el paso 1 del formulario con la persona en el resumen (paso 3). Ahora se avisa al salir del campo, el paso 1 no avanza y, si se rechaza al confirmar, el formulario vuelve al paso del error (`FormularioPorPasos::guardarPorPasos()`) | CP-RF04-04 |
| «Al buscar o filtrar desaparece el botón Desactivar» | Usuarios | **No desaparecía: quedaba fuera de la vista.** La cuenta de la captura tiene un apellido de unas 80 «m» seguidas, sin espacios; la columna del nombre se estiraba y empujaba «Último acceso» y «Acciones» detrás del desplazamiento horizontal. Se comprobó que el botón está en el HTML con búsqueda, rol, estado y URL. Ahora el nombre se parte y la columna tiene tope | Cambio de estilo, sin prueba |
| «Cuando el docente proyecta el QR no hay forma de salir» | Asistencia | La pantalla de proyección no tenía ningún control. Se agregó «Salir de la proyección» (y Esc), discreto para no distraer en el proyector | CP-RF20-02 |

> [!note] Sin probar en un navegador
> Las cuatro correcciones tienen prueba Pest o se verificaron en el HTML, pero no se vieron en un navegador real. Conviene que la QA las repruebe en staging.

## 3. Hallazgos que piden una decisión

Los tres pedían reglas que el vault no definía. **Se decidieron el 30/10/2026** y quedan implementados en el PR #74.

### 3.1 Nombre en cuatro campos, tope de 25 caracteres y normalización (Usuarios)
La QA propone separar el nombre en primer y segundo nombre y primer y segundo apellido, con un tope de unos 25 caracteres por campo y todo guardado en un único formato (mayúsculas o minúsculas). También propone bloquear la «@» en el correo con un aviso inmediato.

- El ER tiene `first_name` y `last_name` ([[Modelo entidad-relación]]). Las cuentas también nacen del perfil de Google (`given_name` y `family_name`, [[ADR-038 Autorregistro sin rol]]) y de la carga masiva (columnas `nombres` y `apellidos`). Ninguna de las dos fuentes trae los cuatro campos.
- El problema de fondo es real: el «Desactivar» oculto (sección 2) lo causó justo un nombre sin espacios.
- **La «@» ya se maneja:** si se pega el correo completo, el formulario se queda con la parte anterior a `@ues.edu.sv`, y otro dominio se rechaza al avanzar. Con esta sesión, el correo repetido también se avisa al salir del campo.

> [!success] Decisión: dos campos con validación ([[ADR-060 Formato de los nombres de usuario]])
> Se mantienen nombres y apellidos. Se rechaza una palabra de más de 25 letras o un campo de más de 60 caracteres, en el alta, la edición y la carga masiva, y todo se guarda como nombre propio («María de los Ángeles»). El ingreso con Google solo normaliza. Caso CP-RF04-23.

### 3.2 Áreas de asistencia por carrera o asignatura (Asistencia)
«Cualquier docente agarra cualquier área; el nombre no importa y no se puede asignar a una carrera o materia.»

- Es el **diseño actual de [[RF26]]**: el QR vale dentro de **cualquier área activa**, que funciona como una geocerca del campus. Al abrir una sesión presencial se guarda la primera área activa, pero la validación acepta todas.
- `attendance_areas` no tiene carrera ni asignatura en el ER. Asignarlas exigiría una ADR y una migración, y además que el docente elija el área (o el aula) al abrir la sesión.

> [!success] Decisión: se mantiene y se explica
> Las áreas siguen siendo una geocerca del campus. La pantalla «Áreas de asistencia» lo dice ahora con un aviso, y [[RF26]] lo deja como regla de negocio.

### 3.3 «Colas» e «Integraciones» llevan a Horizon (Administración)
Los dos botones abren el panel de Laravel Horizon, que no es una vista del sistema, y el módulo de Integraciones le resulta ambiguo a personal administrativo.

- Es así por diseño: el botón «Monitoreo de colas» abre `horizon.index`, protegido por el único Gate permitido (`viewHorizon`, [[ADR-026 Autorización sin Policies]]).
- Es una herramienta técnica: tiene sentido para Soporte Técnico, no tanto para un Administrador académico.

> [!success] Decisión: explicarlo y abrirlo aparte
> En el menú, «Colas» pasa a llamarse «Monitoreo técnico». Tanto esa opción como el botón «Monitoreo técnico (Horizon)» de Integraciones abren en otra pestaña, con el ícono de enlace externo. Integraciones explica para qué sirve cada credencial y el panel. No cambian los permisos.

## 4. Por confirmar en staging

- **«El estudiante sin dispositivo registrado no sabe cómo registrarlo.»** Desde el 27/10 (commit `69312f1`, [[RF27]]) hay un aviso al entrar con el botón «Registrar dispositivo». Es probable que la QA probara una versión anterior. Queda un hueco: el rechazo al escanear (`invalid_device`) dice «Regístrelo en Mi perfil», pero no trae enlace. **Mejora propuesta:** un botón directo a Mi perfil → Dispositivo en ese mensaje.

## 5. Lo que la QA marcó como correcto

Crear la propia cuenta de administrador, editar datos de usuario (con la salvedad de la sección 3.1), buscar usuarios por nombre y por correo, ver los seis roles y asignar permisos, y la auditoría: registro de inicio de sesión, cambios con fecha y hora, filtros y exportación.
