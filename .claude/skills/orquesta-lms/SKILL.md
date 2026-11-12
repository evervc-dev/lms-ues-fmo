---
name: orquesta-lms
description: Implementa un requisito (RFxx) del LMS UES en modo orquesta. Pregunta lo no definido, presenta un plan y espera aprobación, delega en subagentes, verifica contra el vault docs/ y documenta el progreso. Úsala cuando el usuario pida implementar, continuar o corregir un RF, un flujo o un caso de prueba CP-RFxx.
---

# Orquesta LMS: implementar un RF

Sigue `.ai/guidelines/orquesta.md`. Este es el procedimiento paso a paso.

## Entrada
Un identificador `RFxx`, un flujo o un `CP-RFxx-NN`, y el `OP#<ID>` de su paquete de trabajo. Si falta, pregunta cuál.

## Pasos
1. **Contexto.** Lanza `explorador-lms` para leer:
   - `docs/03 Requisitos/Funcionales/RFxx.md` (criterios, reglas, ADR)
   - `docs/10 Calidad/Casos de prueba/CP-RFxx.md`
   - el flujo de `docs/08 Flujos/` relacionado
   - la sección del módulo en `docs/07 Datos/Modelo entidad-relación.md`
   - los permisos del RF en `docs/04 Roles/Matriz RBAC.md`
   - el estado actual del código y del esquema (Boost: `database-schema`; rutas con `php artisan route:list`)
2. **Dudas.** Lista lo que el vault no define y pregúntalo: opciones + *(Recomendado)*, máximo 4 por ronda.
3. **Plan.** Presenta el plan con el formato de la guía y **espera aprobación**.
4. **Estado.** Pon el RF en `estado: en-desarrollo` en el vault y la tarea en *En progreso* en OpenProject (`scripts/openproject.py estado <ID> "En progreso"`).
5. **Ejecución:**
   - `implementador-lms`: migraciones → modelos → Services (permiso + alcance) → Livewire.
   - `probador-lms`, en paralelo cuando se pueda: una prueba Pest por CP, con su ID.
   - Corre `composer check`.
6. **Revisión.** `revisor-lms` contrasta el diff con el RF, las ADR, la Matriz RBAC y la DoD, y devuelve hallazgos con evidencia. Corrige y repite hasta que no queden hallazgos.
7. **Documentación:**
   - OpenProject: `comentar <ID>` con el resumen, y `estado <ID> "En revisión"` al abrir el PR.
   - Vault: RF → `implementado`; CP → `pasa` o `automatizado`; decisiones a la Bitácora.
   - Borrador del PR con `RFxx · … (OP#<ID>)`, los CP cubiertos y las decisiones.
8. **Cierre.** Resume en 5 líneas o menos lo hecho, la evidencia (pruebas y comandos) y lo pendiente.
