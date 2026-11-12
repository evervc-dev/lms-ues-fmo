---
tipo: prueba
rf: "[[RF11]]"
nombre: Gestión de materiales, unidades y recursos
prioridad: Media-Alta
nivel: feature
casos: 8
estado: automatizado
---

# CP-RF11 · Gestión de materiales, unidades y recursos

Casos derivados de los criterios de aceptación de [[RF11]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF11-01 | **Dado** un estudiante no matriculado en la asignatura, **cuando** abre un recurso, **entonces** se rechaza. | automatizado |
| CP-RF11-02 | **Dado** el docente, **cuando** arrastra un archivo a la unidad, **entonces** se sube y valida tipo y tamaño. | automatizado |
| CP-RF11-03 | **Dado** un PDF, **entonces** el docente elige si se descarga o se ve incrustado en la plataforma. | automatizado |
| CP-RF11-04 | **Dado** un estudiante, **cuando** busca por nombre o tipo, **entonces** encuentra el recurso en menos de 3 s. | automatizado |
| CP-RF11-05 | **Dado** las unidades del curso, **entonces** se muestran como acordeones colapsables. | automatizado |
| CP-RF11-06 | **Dado** el docente, **cuando** sube una carpeta, **entonces** se conserva su estructura y el estudiante la descarga completa como .zip. | automatizado |
| CP-RF11-07 | **Dado** el editor de páginas, **cuando** el docente aplica negrita, cursiva o centrado, **entonces** se guarda como HTML sanitizado, sin scripts. | automatizado |
| CP-RF11-08 | **Dado** un recurso o una unidad eliminados, **entonces** van a la papelera y el docente puede restaurarlos durante 30 días; después se borran definitivamente. | automatizado |
> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF11-01 …')`.
