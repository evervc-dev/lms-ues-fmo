---
tipo: prueba
rf: "[[RF12]]"
nombre: Reproducción de video integrada
prioridad: Media
nivel: feature
casos: 2
estado: pendiente
---

# CP-RF12 · Reproducción de video integrada

Casos derivados de los criterios de aceptación de [[RF12]]. Nivel: **feature**. Ver [[Estrategia de pruebas]].

| ID | Caso (Dado / Cuando / Entonces) | Estado |
|---|---|---|
| CP-RF12-01 | **Dado** un enlace de YouTube o Drive, **cuando** el docente lo agrega, **entonces** se muestra incrustado como reproductor. | automatizado |
| CP-RF12-02 | **Dado** un enlace que no se puede incrustar, **entonces** se muestra como enlace externo. | automatizado |
> Al automatizar un caso, nombra la prueba Pest con su ID: `it('CP-RF12-01 …')`.
