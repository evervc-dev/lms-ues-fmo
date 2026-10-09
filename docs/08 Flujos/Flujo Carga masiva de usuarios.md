---
tipo: flujo
nombre: Carga masiva de usuarios
modulo: "[[Identidad y acceso]]"
rf: ["[[RF04]]"]
estado: revisado
---

# Flujo · Carga masiva de usuarios

**Actores:** [[Administrador]] y [[Soporte Técnico]], ambos de cualquier carrera ([[ADR-047 Carreras visibles y carga masiva sin el Coordinador]], que modifica [[ADR-030 Matrícula ampliada]]; el Coordinador ya no importa). **Cuándo:** al iniciar cada período, con los estudiantes de nuevo ingreso.

## Formato (Excel, también se acepta CSV)
| Columna | Obligatoria | Validación |
|---|---|---|
| `carnet` | No | Sale del correo (`rr19118@ues.edu.sv` → `rr19118`); si viene, debe coincidir. Único |
| `nombres` | Sí | |
| `apellidos` | Sí | |
| `correo` | Sí | Único; termina en `@ues.edu.sv` |
| `rol` | Sí | Uno de los 6 roles |
| `carrera` | Para estudiantes | Debe existir en el catálogo |
| `plan` | Para estudiantes | `98` o `25` |
| `ciclo` | Para estudiantes | Número de ciclo actual |

```mermaid
flowchart TD
  A[Admin o Soporte sube el Excel] --> B{¿Formato y encabezados válidos?}
  B -- No --> X[Rechazo total con la lista de columnas faltantes]
  B -- Sí --> C[import_batches + job en cola fila por fila]
  C --> D{¿Fila válida?}
  D -- Correo fuera de dominio / carrera ajena o inexistente / dato vacío --> R[import_batch_errors con motivo]
  D -- Correo o carnet ya existe --> O[Fila omitida: duplicado]
  D -- Sí --> N[Crea users + student_profiles + student_careers, asigna rol]
  N --> Au[Auditoría: alta masiva]
  R & O & Au --> I[Informe: creados / omitidos / rechazados]
  I --> M[Notificación al Admin con el informe descargable]
```

## Reglas
- Cada fila va en su propio SAVEPOINT: una fila mala no detiene las demás; se guardan las válidas y se reportan las erróneas.
- El Administrador y Soporte dan de alta estudiantes de **cualquier carrera**; Soporte no puede crear otros roles ([[ADR-037 Jerarquía de creación y asignación de roles]]).
- No se envían contraseñas: el usuario entra con Google ([[Flujo Inicio de sesión con Google]]).
- Volver a subir el mismo archivo es seguro: los duplicados se omiten.
