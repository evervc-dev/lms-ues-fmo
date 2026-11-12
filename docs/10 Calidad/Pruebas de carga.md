---
tipo: calidad
estado: borrador
rnf: ["[[RNF03]]", "[[RNF10]]", "[[RNF12]]"]
---

# Pruebas de carga (k6)

Los scripts van en `tests/load/` del repositorio y se corren antes de cada release contra **staging** ([[Entornos]]).

| Escenario | Objetivo | Carga | Umbral (falla si no se cumple) |
|---|---|---|---|
| **Navegación general** | [[RNF03]] | Rampa hasta 500 usuarios virtuales en 5 min, sostenidos 10 min: panel, curso, recurso, notas | `p(95) < 3000 ms`, errores < 1 % |
| **Examen simultáneo** | [[RNF10]] | 100 estudiantes inician el mismo cuestionario; guardan cada 30 s durante 20 min | Carga de pregunta `p(95) < 2000 ms`, 0 guardados perdidos |
| **Pico de asistencia QR** | [[RF20]] | 60 escaneos en 2 min en una misma sesión, repetido en 5 secciones a la vez | `p(95) < 3000 ms`, 0 registros duplicados |
| **Notificación masiva** | [[RNF12]] | Publicar notas a una sección de 60 | En la plataforma < 30 s, correos en cola < 5 min |

## Notas
- El inicio de sesión de Google no se prueba con carga: se usa el inicio de sesión de desarrollo, que existe en staging y **nunca** en producción.
- Los usuarios virtuales usan cuentas sembradas con datos falsos.
- Los resultados se adjuntan al PR de `test` → `main`.
