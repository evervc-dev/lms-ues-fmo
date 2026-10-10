---
tipo: propuestas
estado: revisado
actualizado: 2026-10-10
---

# Requisitos propuestos

Estas funciones aparecían en el **alcance** (`Alcance - Aula virtual.pdf`), en la **matriz de roles** (`Roles y permisos.pdf`) o en las entrevistas, pero **no tenían un RF** en `Requerimientos.pdf`. El equipo las resolvió el 10/10/2026.

| ID | Función | Fuente | Resolución |
|---|---|---|---|
| P01 | Auto-matriculación con clave | Alcance; Roles | ✅ Nuevo **[[RF29]]** |
| P02 | Papelera de reciclaje de 30 días | Alcance; Roles | ✅ Fusionado en **[[RF11]]** |
| P03 | Subir carpetas y descargarlas en .zip | Alcance | ✅ Fusionado en **[[RF11]]** |
| P04 | Editor de páginas HTML | Alcance; Roles | ✅ Fusionado en **[[RF11]]** |
| P05 | Foros con hilos anidados | Alcance; Roles; [[Entrevista E6]] | ✅ Nuevo **[[RF30]]** |
| P06 | Mensajería condicional desde el libro de notas | Alcance; Roles | ✅ Nuevo **[[RF31]]** |
| P07 | Finalización automática por reglas | Alcance | ✅ Fusionado en **[[RF18]]** |
| P08 | Mesa de ayuda y diagnóstico de Soporte | Roles | ❌ Mesa de ayuda no aprobada. El **diagnóstico QR de solo lectura** sí se agregó a [[RF20]] (ADR-032) |
| P09 | KPIs de desempeño docente | Roles | ✅ Nuevo **[[RF33]]** |
| P10 | Supervisión de solo lectura del Coordinador | Roles | ✅ Fusionado en **[[RF03]]** y **[[RF24]]** |
| P11 | Clases canceladas y cambios de modalidad | [[Entrevista E6]] | ✅ Nuevo **[[RF32]]** |
| P12 | Calificación anónima | Alcance; Roles | ✅ Fusionado en **[[RF15]]** y **[[RF17]]** ([[ADR-032 Ajustes por el documento de roles]]) |
| P13 | Clonar un curso completo | Alcance | ✅ Ya cubierto por **[[RF14]]** |
| P14 | Validación de notas finales (Coordinador) y autorización de cierres (Jefe) | Roles (enfoques) | ✅ Nuevo **[[RF34]]** (ADR-032) |

> [!note] Prioridades de los RF nuevos
> Las prioridades de RF29 a RF33 son una propuesta: RF29 Media-Alta y el resto Media. Hay que confirmarlas.

## Fuera de alcance (confirmado en el alcance)
- Aplicación móvil: todo funciona desde la web responsive.
- Contenido interactivo H5P y plugins externos.
- Mensajería interna o chat (E6 la pidió, pero se descartó).
- Editor de código o notebooks integrados (E7) e inteligencia artificial (E2, E5, E7): no aparecen en ningún documento de alcance.
