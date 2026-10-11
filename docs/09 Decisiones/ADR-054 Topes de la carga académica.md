---
tipo: adr
id: ADR-054
estado: pendiente
fecha: 2026-10-27
afecta: ["[[RF08]]", "[[ADR-037 Jerarquía de creación y asignación de roles]]"]
fuentes: ["Informe de QA del rol Administrador (27/10/2026), observaciones 3.4.2 y Figuras 17 y 30", "Decisión del usuario (27/10/2026)"]
---

# ADR-054 · Topes de la carga académica

> [!warning] Pendiente de aceptar
> **Los números son provisionales.** Los fija la Jefatura de Departamento, no el equipo de desarrollo: se eligen 2 docentes y 10 horas para cerrar el hallazgo, y quedan en `system_settings` para cambiarlos sin migración.

## Contexto
El **informe de QA** (27/10/2026) encontró dos ausencias en la carga académica: se asignaron **7 docentes a una misma sección** (Figura 30, ACC115-02) y se registró una **sección con 17 horas semanales** (Figura 17). Ni [[RF08]] ni el reglamento citado por la prueba fijan un máximo, y [[ADR-037 Jerarquía de creación y asignación de roles]] solo regula el cupo de estudiantes, entre 1 y 200.

## Decisión
*Decidida por el usuario el 27/10/2026, con números provisionales.*

1. **Máximo de docentes por sección: 2**, en `system_settings.max_teachers_per_section`. Cubre al docente responsable y a un auxiliar.
2. **Máximo de horas semanales por sección: 10**, en `system_settings.max_weekly_hours_per_section`, sumando los bloques de su horario.
3. **Se valida en los Services** (`CargaAcademicaService` y `HorarioService`), con el mensaje que nombra el tope y el valor actual.
4. **No se aplica retroactivamente:** las secciones que ya pasan el tope siguen como están; el tope se comprueba al agregar un docente o un bloque de horario.

## Consecuencias
- Si la Jefatura fija otros números, se cambian en `system_settings` sin tocar código ni migraciones.
- **Riesgo:** una materia con laboratorio y dos grupos de práctica podría necesitar más de 2 docentes o más de 10 horas. Por eso son parámetros y no un `CHECK` en la base.
- Afecta a [[RF08]] y a sus casos de prueba.
