---
tipo: adr
id: ADR-015
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RNF01]]", "[[RNF03]]", "[[RNF06]]", "[[Stack y paquetes]]"]
fuentes: []
---

# ADR-015 · Plataforma base

## Decisión
*Decidido por el equipo el 10/10/2026:*
- **Laravel 12 + PHP 8.4 + Livewire 4**, con Redis 7. *Actualizado el 10/10/2026: se decidió Livewire 3 y se cambió a **Livewire 4** al crear el repositorio, porque es la versión estable actual (4.4).*
- **PostgreSQL 17** en producción.
- Colas en **Redis + Horizon**.
- **Sesiones en PostgreSQL**, para listarlas y cerrarlas en remoto ([[RNF01]]).

## Por qué PostgreSQL
Tiene restricciones `EXCLUDE` con rangos, que impiden en la base de datos los choques de horario de **aula** ([[RF08]]). Los choques de **docente** se validan en el Service ([[Modelo entidad-relación]]). También permite roles con permisos finos, como la auditoría sin UPDATE ni DELETE ([[Seguridad]]), y tiene buen soporte de JSON y CHECK.

## Consecuencias
- Las pruebas corren en SQLite ([[ADR-013 Pruebas con Pest]]); lo propio de PostgreSQL se verifica en staging ([[Capacidad y rendimiento]]).
