---
tipo: adr
id: ADR-085
estado: pendiente
fecha: 2026-11-22
afecta: ["[[RF06]]", "[[RF17]]", "[[ADR-030 Matrícula ampliada]]", "[[ADR-062 Notas y entregas en un cambio de grupo]]"]
fuentes: ["Revisión del libro de calificaciones en producción", "Decisión del usuario del 22/11/2026"]
---

# ADR-085 · Cuatro cómputos por período

> [!info] Pendiente de aceptar por el equipo.

## Contexto
- La plantilla de cómputos del período tenía **tres** cómputos de 30 %, 30 % y 40 %. Ningún documento del vault fijaba ese número: estaba escrito en `InstitutionSeeder` y en `PeriodoService::crear()`.
- La facultad trabaja con **cuatro** cómputos. Un grupo copia la plantilla de su período al empezar (ADR-062), así que en ningún grupo podía aparecer un Cómputo 4.

## Opciones
- **A. Cuatro cómputos de 25 %; el primero dura más y el último menos.**
- **B. Cuatro cómputos de 25 %, en tramos iguales.**
- **C. Mantener tres.**

## Decisión
**A.** *Decidida por el usuario el 22/11/2026.*
- Los cuatro pesan **25 %** en la nota por defecto. Igual que antes, son provisionales hasta el reglamento académico (ADR-030).
- Son siempre cuatro: el docente no agrega ni quita cómputos, pero ajusta sus pesos en Notas > Pesos, con dos reglas: suman exactamente 100 % y **ninguno baja de 20 %** (`ComputoService::PESO_MINIMO`). Así, 25 · 25 · 20 · 30 vale y 15 % no; ninguno pasa de 40 %. *Decidido por el usuario el 22/11/2026.*
- Duración dentro del período: Cómputo 1 **30 %**, Cómputo 2 **25 %**, Cómputo 3 **25 %** y Cómputo 4 el resto (**20 %**). Cada uno empieza el día siguiente al fin del anterior, y el último termina el último día del período. En 2026-II quedan así: 27/07–05/09, 06/09–10/10, 11/10–13/11 y 14/11–11/12.
- La plantilla tiene una sola fuente, `PeriodoService::COMPUTOS` y `PeriodoService::plantillaComputos()`, que usan tanto la creación de períodos como el seeder.

## Consecuencias
- Los períodos nuevos y una base sembrada de nuevo ya traen cuatro cómputos.
- Los períodos y grupos que ya existen conservan tres hasta que se corrijan sus datos: hay que agregar el Cómputo 4 a la plantilla y a cada grupo, y volver a repartir pesos y fechas. Los grupos con notas cambian su nota acumulada, porque los pesos pasan de 30/30/40 a 25 cada uno.
