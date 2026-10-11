---
tipo: adr
id: ADR-027
estado: aceptada
fecha: 2026-10-10
afecta: ["[[Modelo entidad-relación]]", "[[Seguridad]]", "[[Estrategia de pruebas]]", "[[ADR-013 Pruebas con Pest]]", "[[ADR-016 Paquetes]]", "[[ADR-026 Autorización sin Policies]]"]
fuentes: ["Diseño de Base de Datos – Sistema de Gestión Académica (PostgreSQL + Laravel), @Alex, 09/10/2026"]
---

# ADR-027 · Base de datos híbrida: restricciones y auditoría en PostgreSQL, operaciones en los Services

> [!note] Actualizada por [[ADR-033 Migraciones adaptativas]]
> Las pruebas corren por defecto en **SQLite** y en la CI también en **PostgreSQL**; la capa propia de PostgreSQL se aplica solo con `DB_CONNECTION=pgsql`.

## Contexto
El diseño de @Alex pone las reglas críticas en la base de datos: funciones `sp_*` para matricular, marcar QR y cerrar períodos, triggers de auditoría y RLS. Nuestra arquitectura las ponía en los Services de Laravel ([[ADR-026 Autorización sin Policies]]) y probaba en SQLite ([[ADR-013 Pruebas con Pest]]).

## Decisión
*Decidido por el equipo el 10/10/2026:* **enfoque híbrido**.

| Capa | Qué hace |
|---|---|
| **PostgreSQL**, garantía final | Restricciones declarativas: `UNIQUE`, `CHECK`, FK compuestas, índices únicos parciales, `EXCLUDE` (choques de aula). **Triggers solo para auditoría e inmutabilidad**: `fn_audit_row` escribe `audit_logs` con el actor; `fn_block_mutation` impide editar o borrar logs e historiales; `fn_block_delete` impide borrar usuarios, matrículas, notas y asistencia. RLS sobre `audit_logs` para el Jefe ([[ADR-029 Auditoría departamental del Jefe]]) |
| **Services de Laravel** | Operaciones de varios pasos dentro de una transacción con bloqueo de fila: matricular, cambiar de sección, marcar QR, cerrar el período, calcular la nota final. También el **alcance** con `App\Support\Alcance` |
| **`App\Support\DbContext`** | Toda escritura en tablas auditadas pasa por aquí. Abre la transacción, fija `app.user_id`, `app.ip`, `app.request_id` y `app.reason` con `set_config(..., true)` y reintenta ante interbloqueos. Seeders y jobs declaran `app.system_job = on` |
| **`App\Support\DbError`** | Traduce los SQLSTATE propios (`AC1xx`…) y los de PostgreSQL (`23505`, `23P01`) a mensajes en español, de usted |

No se usan funciones `sp_*` para la lógica de negocio: se quedan en los Services, donde se prueban y depuran mejor.

## Consecuencias
- **Pruebas:** las suites que tocan la base de datos (Feature y Browser) corren **en PostgreSQL**, una base `lms_test` local y un servicio `postgres` en la CI. Las pruebas Unit y Arch no usan BD. Esto reemplaza "SQLite en memoria" de [[ADR-013 Pruebas con Pest]].
- **Auditoría:** reemplaza `owen-it/laravel-auditing` de [[ADR-016 Paquetes]] por triggers. La auditoría queda garantizada aunque se escriba fuera de Laravel.
- **Migraciones:** lo que el Schema Builder no soporta (EXCLUDE, índices parciales, triggers, RLS, particiones, vistas) va en SQL dentro de `database/sql/` y se ejecuta desde una migración con `DB::unprepared()`. Funciones y triggers usan `CREATE OR REPLACE`.
- **Roles de PostgreSQL:** `app_migrator` (DDL) y `app_rw` (aplicación), este último sin UPDATE ni DELETE en logs.
