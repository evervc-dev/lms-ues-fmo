---
tipo: prueba
rf: "[[RF20]]"
nombre: Generación y validación de código QR para asistencia
prioridad: Alta
nivel: browser + feature
casos: 13
estado: pasa
---

# CP-RF20 · Generación y validación de código QR para asistencia

Casos derivados de los criterios de aceptación de [[RF20]]. Nivel: **browser + feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF20-01 | **Dado** un token que ya rotó dos veces (vale el vigente y el inmediatamente anterior, [[ADR-046 Lector QR en la página y rotación por polling]]), **cuando** se escanea, **entonces** se rechaza con «Código vencido; escanee el QR actual». | pasa |
| CP-RF20-02 | **Dado** una sesión cuya vigencia (5–15 min) terminó, **cuando** se escanea, **entonces** se rechaza con «La sesión de asistencia está cerrada». | pasa |
| CP-RF20-03 | **Dado** un estudiante que ya usó sus 3 intentos en la sesión, **cuando** escanea otra vez, **entonces** se rechaza y se le indica pedir un código temporal. | pasa |
| CP-RF20-04 | **Dado** el docente, **cuando** abre la sesión, **entonces** elige una vigencia entre 5 y 15 minutos; fuera de ese rango se rechaza. | pasa |
| CP-RF20-05 | **Dado** un estudiante que ya registró asistencia en la sesión, **cuando** escanea de nuevo, **entonces** se rechaza. | pasa |
| CP-RF20-06 | **Dado** que el navegador no entrega la ubicación (permiso negado), **entonces** se rechaza e indica activar el GPS o pedir un código temporal ([[RF28]]). | pasa |
| CP-RF20-07 | **Dado** una ubicación fuera del área autorizada, **entonces** se rechaza con «Fuera del área de asistencia». | pasa |
| CP-RF20-08 | **Dado** un dispositivo que no es el autorizado o un token de dispositivo inválido, **entonces** se rechaza. | pasa |
| CP-RF20-09 | **Dado** un estudiante no matriculado en la sección, **entonces** se rechaza. | pasa |
| CP-RF20-10 | **Dado** cada intento, exitoso o no, **entonces** se guarda con su motivo exacto (`attendance_scan_attempts`) y el docente ve los rechazos en la sesión. | pasa |
| CP-RF20-11 | **Dado** Soporte Técnico, **cuando** abre el diagnóstico de asistencia, **entonces** ve en solo lectura los intentos y su motivo exacto (sin notas ni contenidos) ([[ADR-032 Ajustes por el documento de roles]]). | pasa |
| CP-RF20-12 | **Dado** un estudiante sin teléfono, **entonces** el docente lo registra con código temporal ([[RF28]]) o de forma manual ([[RF19]]). | pasa |
| CP-RF20-13 | **Dado** Soporte en el diagnóstico, **cuando** busca por nombre, fija un rango de fechas o un motivo y exporta, **entonces** la búsqueda por nombre solo trae a quien empieza alguna palabra con lo escrito, el rango y el motivo se respetan y el CSV sale sin coordenadas. | pasa |

> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF20-01 …')`.
>
> **27/10/2026:** los casos pasan a `pasa` con la corrida contra **PostgreSQL 17**: 52 pruebas en verde (`vendor/bin/pest --filter="CP-RF20"`), incluidas las 6 de navegador, ninguna saltada. Hasta entonces parte de ellos solo existía como prueba escrita, porque se saltaban en SQLite y el repositorio no tenía CI que los repitiera.
