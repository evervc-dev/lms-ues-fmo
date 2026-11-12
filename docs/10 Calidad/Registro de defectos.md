---
tipo: calidad
estado: revisado
rnf: ["[[RNF14]]"]
adr: ["[[ADR-025 OpenProject como gestor del trabajo]]"]
---

# Registro de defectos

Los defectos se registran en **OpenProject**, con el tipo **Bug** ([[ADR-025 OpenProject como gestor del trabajo]]). GitHub Issues no se usa.

## Cómo se registra
- **Tipo:** Bug, **hijo del Feature del RF** afectado, para que se vea en el árbol y en el Gantt.
- **Campos:** `RF`, `Módulo` y `Equipo` (los mismos campos personalizados de [[OpenProject]]).
- **Prioridad = severidad**, ver la tabla.
- Desde la terminal: `python scripts/openproject.py bug --rf RF20 --titulo "…" --severidad Alta`.

## Severidad
| Severidad | Prioridad en OP | Criterio | Ejemplo | Respuesta |
|---|---|---|---|---|
| **Crítica** | Immediate | Pérdida de datos académicos, falla de seguridad o caída total | Se pierden respuestas de un examen; un estudiante ve notas ajenas | Hotfix inmediato a `main` |
| **Alta** | High | Un flujo principal no funciona y no hay alternativa | El QR rechaza a todos | Antes del próximo merge a `main` |
| **Media** | Normal | Falla con alternativa | El reporte de Meet no lee una columna; se corrige a mano | Planificado en el hito |
| **Baja** | Low | Cosmético | Texto mal traducido | Cuando se pueda |

Después del congelado (18/11) solo se trabajan Bugs y tareas de estabilización.

## Plantilla de la descripción
```markdown
**Entorno:** local | staging | producción
**Rol del usuario:**
**Pasos para reproducir:**
1.
**Resultado esperado:**
**Resultado obtenido:**
**Evidencia:** captura, log, ID de auditoría, CP-RFxx-NN que falla
```

## Ciclo
`Nuevo` → `En progreso` → `En revisión` (PR con `OP#id`) → `En test` (merge a `test`) → `Cerrado`.
El PR que lo corrige incluye **una prueba de regresión** que reproduce el defecto.
