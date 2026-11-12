---
tipo: calidad
estado: revisado
---

# Definition of Done

Una historia o RF está **terminado** cuando:

- [ ] Cumple **todos** sus criterios de aceptación (ver la nota del RF).
- [ ] Tiene pruebas Pest para cada criterio, y en navegador si es uno de los flujos críticos ([[Estrategia de pruebas]]).
- [ ] La cobertura global sigue en 70 % o más.
- [ ] Pint y Larastan nivel 5 pasan sin errores.
- [ ] Permisos y alcance aplicados en los Services coinciden con la [[Matriz RBAC]] (sin Policies).
- [ ] Los cambios críticos se auditan ([[RNF02]]) y las operaciones críticas van en transacción ([[RNF08]]).
- [ ] La interfaz está en español, es responsive desde 360 px y no tiene errores graves de accesibilidad ([[RNF05]], [[RNF11]]).
- [ ] Se aprobó el PR a `test`, con 1 revisión y `composer check` en verde en local, con la evidencia en el PR ([[Flujo de ramas y CI]], [[ADR-061 Verificación local sin GitHub Actions]]).
- [ ] El paquete de trabajo de OpenProject está en *En test* y el PR referencia su `OP#<ID>` ([[OpenProject]]).
- [ ] Se actualizó la documentación: estado del RF a `implementado`, casos CP y flujos tocados ([[RNF13]]).
- [ ] Si cambia lo que ve un rol, se actualizó su manual (fase 11).

Un **release** a `main` además exige:
- [ ] La suite completa de regresión en verde ([[RNF14]]).
- [ ] k6 dentro de los umbrales ([[Pruebas de carga]]).
- [ ] 2 aprobaciones.
- [ ] Notas de versión y una etiqueta semver (`vX.Y.Z`).
