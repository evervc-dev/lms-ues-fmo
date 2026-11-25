---
tipo: adr
id: ADR-078
estado: pendiente
fecha: 2026-11-21
afecta: ["[[RF09]]", "[[Matriz RBAC]]", "[[Jefe de Departamento]]", "[[Coordinador]]", "[[ADR-052 Prerrequisitos exigidos al matricular]]"]
fuentes: ["Decisión del usuario del 21/11/2026 (D1 y D4 de la revisión de flujos)"]
---

# ADR-078 · El Jefe resuelve el cambio de grupo y no se validan prerrequisitos

> [!info] Pendiente de aceptar por el equipo. Rechaza [[ADR-052 Prerrequisitos exigidos al matricular]].

## Contexto
- **Prerrequisitos:** el código los exigía al matricular por ADR-052, que seguía pendiente, mientras RF09, [[ADR-030 Matrícula ampliada]] y [[ADR-032 Ajustes por el documento de roles]] (aceptadas) dicen que no se validan. En producción nadie tendrá historial académico al arrancar: nadie podría matricularse en una materia con prerrequisito.
- **Cambio de grupo:** lo aprobaba cualquiera con `matricula.gestionar` (Docente del grupo de origen, Coordinador, Jefe, Administrador y Soporte), mientras RF09 decía que va al Coordinador. En la facultad lo gestiona el Jefe del departamento.

## Decisión
*Decidida por el usuario el 21/11/2026.*
- **D1:** los prerrequisitos **no se validan** al matricular, por ninguna vía. Se quitó `MatriculaService::exigirPrerrequisitos`. El catálogo los sigue registrando (RF07) y la proyección de oferta los usa (RF10).
- **D4:** el cambio de grupo lo resuelve **solo el Jefe del departamento** (alcance D), con el permiso nuevo `matricula.cambio.resolver`. La pestaña «Solicitudes de cambio de grupo» y su contador solo los ve quien tiene ese permiso.

## Consecuencias
- Migración `grant_enrollment_change_resolve_permission`, que concede el permiso en bases con datos, y fila nueva en la Matriz RBAC.
- CP-RF09-03 vuelve a estar vigente; CP-RF09-13 queda sustituido.
- Si más adelante se quiere que también el Coordinador resuelva, basta con darle `matricula.cambio.resolver` con alcance C.
