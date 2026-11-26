---
tipo: adr
id: ADR-082
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF01]]", "[[RF03]]", "[[RF04]]", "[[Matriz RBAC]]", "[[ADR-007 Inicio de sesión con Google]]", "[[ADR-081 Estudiante y Soporte Técnico son roles exclusivos]]", "[[ADR-026 Autorización sin Policies]]"]
fuentes: ["Decisión del usuario del 21/11/2026 para el ambiente de pruebas (staging)"]
---

# ADR-082 · Cuenta superadministradora que ve todo

> [!warning] Pendiente de aceptar por el equipo. **Amplía [[ADR-007 Inicio de sesión con Google]]**, que solo permitía una cuenta local (la de emergencia), y **[[ADR-081 Estudiante y Soporte Técnico son roles exclusivos]]**, que suma un tercer rol exclusivo.

## Contexto
- El equipo de pruebas necesita una cuenta que entre a todas las vistas para revisarlas sin cambiar de usuario.
- ADR-007 limita el inicio de sesión a Google `@ues.edu.sv`, salvo la cuenta de emergencia con contraseña y 2FA.
- ADR-026 prohíbe Gates y Policies, así que no se puede usar `Gate::before()`.

## Opciones
- **A. Ver todo y gestionar lo administrativo:** todas las vistas de consulta con alcance G, más los permisos del Administrador; sin acciones propias del estudiante ni de docencia.
- **B. Todo con alcance G**, incluidas calificar, publicar actas y abrir asistencia.
- **C. «Ver como» otro usuario** (suplantación de solo lectura).

## Decisión
**A.** *Decidida por el usuario el 21/11/2026.*
- Rol `superadmin`, **exclusivo**, de una sola cuenta: `superadmin@ues.edu.sv`.
- Permisos (Matriz RBAC, regla de la columna Super): con el alcance del Administrador, todos los del Administrador; con alcance G, todos los de consulta (`*.ver`, `oferta.estadisticas.ver`, `contenidos.analitica.ver`, `asistencia.diagnostico.ver`, `calificaciones.revision.ver`); con P, los de la propia cuenta (`notificaciones.preferencias`, `perfil.sesiones.cerrar`). Ninguno de acción del estudiante (entregar, resolver, marcar, automatricularse, dispositivo) ni de docencia (calificar, publicar o validar actas, abrir o corregir asistencia, autorizar el cierre).
- **No se asigna desde la interfaz:** no está en `RolService::ROLES`, así que la página de roles no lo ofrece y nadie le cambia los roles a esa cuenta. Se crea con `php artisan superadmin:crear`.
- **Ingreso por contraseña** en una ruta aparte (`/acceso-local`), que no aparece en `/login`. La contraseña sale de `SUPERADMIN_PASSWORD` (nunca del código) y se guarda con hash. Hay límite de intentos y cada intento queda en `login_history`.
- **Sin 2FA**, por decisión del usuario. La cuenta queda protegida solo por la contraseña.
- **Google rechaza esa cuenta** aunque valide el dominio: si TI creara `superadmin@ues.edu.sv`, no daría acceso.
- Ambiente: **staging** (ambiente de pruebas).

## Consecuencias
- Riesgo: una cuenta que lo ve todo, sin 2FA. Usar una contraseña larga en `SUPERADMIN_PASSWORD` y retirar la cuenta (`is_active = false`) antes de producción.
- El menú muestra al superadmin todos los grupos salvo «Estudio».
- Migración que crea el rol con sus permisos en las bases desplegadas.
