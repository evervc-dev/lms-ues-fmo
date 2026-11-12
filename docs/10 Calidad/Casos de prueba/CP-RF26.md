---
tipo: prueba
rf: "[[RF26]]"
nombre: Configuración de áreas de asistencia
prioridad: Alta
nivel: feature
casos: 2
estado: automatizado
---

# CP-RF26 · Configuración de áreas de asistencia

Casos derivados de los criterios de aceptación de [[RF26]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF26-01 | **Dado** el Administrador, **cuando** define un área por centro y radio o por polígono, **entonces** queda disponible para las sesiones de asistencia. | automatizado |
| CP-RF26-02 | **Dado** una ubicación fuera del área, **entonces** el registro se rechaza ([[RF20]]). | automatizado |

> El polígono lo cubren `tests/Feature/Asistencia/AsistenciaTest.php` (CP-RF26-01) y `AsistenciaQrTest.php` (CP-RF26-02, dentro y fuera de un polígono); la geometría, `tests/Unit/Asistencia/GeocercaTest.php` ([[ADR-055 Leaflet y OpenStreetMap para las áreas de asistencia]]).
>
> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF26-01 …')`.
