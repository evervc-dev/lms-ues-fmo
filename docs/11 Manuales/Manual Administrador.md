---
tipo: manual
rol: "[[Administrador]]"
ayuda_ruta: /ayuda/administrador
estado: esqueleto
---

# Manual del Administrador

Esqueleto por tareas, redactado de **usted**. Las capturas se agregan cuando exista la interfaz. Se publica en la app en `/ayuda/administrador` ([[Ayuda en la app]]).

## 1. Ingresar al sistema
*Requisitos: [[RF01]]*

1. Abra la plataforma y pulse **Iniciar sesión con Google**.
2. Elija su cuenta institucional **@ues.edu.sv**. Otras cuentas son rechazadas.
3. Si aparece «Su cuenta no está habilitada», contacte al Administrador: su usuario aún no fue dado de alta.
4. Su sesión se cierra sola tras 30 minutos sin actividad. Desde **Mi perfil → Sesiones** puede cerrar las sesiones abiertas en otros equipos.

> [!example] Captura pendiente
> Pantalla: ingresar al sistema.

## 2. Dar de alta usuarios
*Requisitos: [[RF04]]*

1. Use **Usuarios → Nuevo** o **Importar Excel**. La carga masiva normalmente la hace cada Coordinador para su carrera; usted puede hacerla para cualquiera.
2. Revise el informe: creados, duplicados omitidos y filas rechazadas con su motivo.
3. Desactive en vez de eliminar: el historial se conserva.

> [!example] Captura pendiente
> Pantalla: dar de alta usuarios.

## 3. Configurar roles y permisos
*Requisitos: [[RF03]]*

1. En **Roles y permisos** asigne o revoque permisos; el cambio queda auditado. Ver [[Matriz RBAC]].

> [!example] Captura pendiente
> Pantalla: configurar roles y permisos.

## 4. Gestionar períodos y catálogo
*Requisitos: [[RF06]] · [[RF07]]*

1. Cree el período y avance sus estados: planificación → horarios → asignación docente → en curso → cerrado. Solo puede haber uno en curso.
2. Para **cerrar** el período, todos los departamentos deben haber autorizado su cierre (actas validadas por los Coordinadores).
3. Para **reabrir** un período cerrado, use **Reabrir período** en su detalle y escriba el motivo; se avisa a los Coordinadores y queda auditado. Solo se puede si no hay otro período en curso. Las autorizaciones de cierre se conservan, así que puede volver a cerrarlo sin repetir las actas; al cerrarlo se recalculan las notas finales.
4. Mantenga el catálogo de asignaturas: código único, créditos, departamento y prerrequisitos.

> [!example] Captura pendiente
> Pantalla: gestionar períodos y catálogo.

## 5. Configurar áreas de asistencia
*Requisitos: [[RF26]]*

1. Defina el área del campus por centro y radio, o por polígono, con margen para la imprecisión del GPS.

> [!example] Captura pendiente
> Pantalla: configurar áreas de asistencia.

## 6. Integraciones
*Requisitos: [[RNF09]]*

1. Configure el cliente OAuth de Google, Calendar y el correo. El procedimiento de la cuenta de emergencia está en la nota interna [[Operación - Cuenta de emergencia]].

> [!example] Captura pendiente
> Pantalla: integraciones.
