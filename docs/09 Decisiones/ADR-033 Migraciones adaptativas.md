---
tipo: adr
id: ADR-033
estado: aceptada
fecha: 2026-10-11
afecta: ["[[Modelo entidad-relación]]", "[[ADR-027 Base de datos híbrida]]", "[[Estrategia de pruebas]]", "[[Setup del entorno]]"]
fuentes: []
---

# ADR-033 · Migraciones adaptativas: SQLite por defecto, PostgreSQL completo

## Decisión
*Decidido por el equipo el 11/10/2026:* las migraciones funcionan **primero en SQLite**. Si la conexión es `pgsql`, aplican además la capa propia de PostgreSQL.

| Capa | SQLite (por defecto) | PostgreSQL (`DB_CONNECTION=pgsql`) |
|---|---|---|
| Tablas, FK y FK compuestas, únicos | ✅ | ✅ |
| Índices únicos parciales (`WHERE`) | ✅ (misma sintaxis) | ✅ |
| Vistas `v_section_capacity` y `v_unit_progress` | ✅ | ✅ |
| `citext`, `CHECK` | — | ✅ |
| `EXCLUDE` de choques de aula | — | ✅ |
| Triggers de auditoría, inmutabilidad, no borrado y período cerrado | — | ✅ |
| RLS de `audit_logs` (Jefe solo su departamento) | — | ✅ |
| Particiones mensuales de `audit_logs` y `resource_access_logs` | — | ✅ |
| `v_teacher_kpis`, `mv_offer_stats` | — | ✅ |

## Implementación
- `App\Support\Database\Adaptive`: `partialUnique()` en ambos motores; `check()`, `citext()` y `pgsqlFile()` solo en PostgreSQL.
- El SQL de PostgreSQL está en `database/sql/pgsql/00…05`, con su reversa en `99_drop.sql`.
- **Cuidado en SQLite:** al alterar una tabla (por ejemplo, agregar una FK con `Schema::table`), Laravel la reconstruye y recrea sus índices **sin la condición `WHERE`**. Después de cada `Schema::table` sobre una tabla con índices parciales, llame a `Adaptive::restorePartialUnique()`. Hoy aplica a `users` (`import_batch_id`).
- `App\Support\DbContext` fija el actor de la auditoría solo en PostgreSQL y lo limpia al terminar. En SQLite solo abre la transacción.

## Consecuencias
- **Desarrollo y pruebas locales sin servidor de BD:** `.env.example` y `phpunit.xml` usan SQLite.
- **La CI corre la suite en los dos motores** (matriz `sqlite` y `pgsql`). Las pruebas que dependen de triggers o RLS se saltan en SQLite.
- En SQLite, las reglas que dependen de `CHECK` o `EXCLUDE` las garantiza solo la validación del Service. **Producción y staging usan PostgreSQL**.
- Actualiza [[ADR-027 Base de datos híbrida]]: las pruebas ya no corren solo en PostgreSQL.
- Verificado el 11/10/2026: 14 migraciones, ida y vuelta en ambos motores; en PostgreSQL, 101 tablas, 27 triggers, 71 CHECK, 1 EXCLUDE y RLS probada con un rol sin privilegios.
