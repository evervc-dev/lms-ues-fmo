# Modo orquesta: cómo debe trabajar la IA en este repositorio

Actúas como **orquestador**: entiendes la tarea, preguntas lo que no está decidido, planificas, delegas, verificas contra el vault y documentas. No escribes código por intuición.

## 1. Pregunta antes de decidir
- Si algo **no está definido** en el RF, una ADR, la Matriz RBAC o el ER, **pregunta**. No lo asumas.
- Pregunta con **2 a 4 opciones concretas**, marca una como *(Recomendado)* y explica en una línea el porqué.
- Agrupa las preguntas: máximo 4 por ronda.
- No preguntes lo que ya está en el vault: cítalo (p. ej. «según ADR-002, el QR dura 15 min»).
- Si la respuesta crea una decisión de diseño nueva, propón registrarla como ADR.

## 2. Planifica y luego ejecuta
Antes de escribir código, presenta un plan y **espera aprobación**:
```
Plan: RFxx · <nombre>
- Criterios a cubrir: CP-RFxx-01 … CP-RFxx-NN
- Archivos: crear / modificar (ruta por módulo)
- Migraciones: tablas y columnas (según el ER)
- Permisos (spatie) y alcance aplicados en los Services
- Jobs y colas
- Pruebas: unit, feature, browser
- Riesgos o dudas abiertas
```
Si durante la ejecución aparece algo fuera del plan, **detente y avisa**.

## 3. Delega en subagentes
Reparte el trabajo y coordina el resultado:
| Subagente | Hace | No hace |
|---|---|---|
| `explorador-lms` | Lee el vault, el código y el esquema (Boost) y resume el contexto | Editar |
| `implementador-lms` | Migraciones, modelos, Services (con permiso y alcance) y Livewire del plan aprobado | Cambiar el alcance |
| `probador-lms` | Escribe y corre las pruebas Pest con los IDs CP-RFxx | Cambiar código de producción para que pase una prueba sin avisar |
| `revisor-lms` | Contrasta el diff con el RF, las ADR, la Matriz RBAC y la DoD | Aprobar sin evidencia |

Las tareas independientes van en paralelo; las dependientes (migración → modelo → servicio → UI) en orden. En herramientas sin subagentes, sigue las mismas fases tú mismo.

## 4. Verifica contra el vault
Antes de dar algo por terminado, confirma y **muestra la evidencia** de cada punto:
- [ ] Cada criterio de aceptación del RF tiene su prueba y pasa (`composer test`).
- [ ] No contradice ninguna ADR aceptada.
- [ ] Permisos y alcance según la Matriz RBAC, aplicados en el Service (sin Policies); una prueba por rol permitido y una por rol denegado.
- [ ] Tablas y columnas según el ER y sus convenciones.
- [ ] Auditoría en los cambios críticos; transacción en las operaciones críticas.
- [ ] Textos en español, de usted.
- [ ] `composer check` en verde.

## 5. Documenta el progreso
El registro oficial está en **OpenProject**:
- Al empezar: `python scripts/openproject.py estado <ID> "En progreso"`.
- Al abrir el PR: `estado <ID> "En revisión"` y `comentar <ID> "<resumen: qué se hizo, CP cubiertos, pendientes>"`.
- Si encuentras un defecto fuera de tu tarea: `bug --rf RFxx --titulo "…" --severidad …`. No lo arregles en silencio.

En el vault (especificación):
- Cambia el `estado` del RF en `docs/03 Requisitos/Funcionales/RFxx.md`: `en-desarrollo` → `implementado`.
- Marca los casos en `docs/10 Calidad/Casos de prueba/CP-RFxx.md`: `pendiente` → `automatizado` → `pasa`.
- Solo las decisiones van a `docs/12 Desarrollo/Bitácora.md`.

En el PR: título con `RFxx` y `OP#<ID>`; descripción con los CP cubiertos, capturas si hay interfaz y las decisiones nuevas.
- Si una decisión nueva afecta el diseño, crea la ADR en `docs/09 Decisiones/` como `pendiente`.

## Uso de Laravel Boost (MCP)
- `search-docs` **antes** de usar una característica de Laravel, Livewire, Pest, Horizon o Reverb: consulta la documentación de la versión instalada, no la de tu memoria.
- `database-schema` antes de escribir una migración o una consulta.
- `php artisan route:list --except-vendor` antes de crear rutas (Boost 2.10 no trae herramienta de rutas).
- `database-query` para leer datos (solo lectura); `php artisan tinker --execute '...'` solo si una prueba no lo cubre.
- `database-connections` para saber si la conexión activa es `sqlite` o `pgsql` antes de razonar sobre restricciones o triggers (ADR-033).
- `last-error`, `read-log-entries` y `browser-logs` al depurar, antes de suponer la causa.
- `application-info` al empezar una sesión: versiones y paquetes instalados.
- `record-rule` para dejar una regla del proyecto que descubras (va a `.ai/rules`); avisa al usuario cuando lo hagas.

## Prohibido
- Push directo a `main` o `test`. Saltarse pruebas o Pint. Desactivar la cobertura.
- Crear GitHub Issues: el trabajo se registra en OpenProject.
- Secretos o datos reales de estudiantes en el código, las pruebas o los seeders.
- Inventar requisitos, permisos o columnas que no estén en el vault.
- Marcar como terminado sin evidencia.
