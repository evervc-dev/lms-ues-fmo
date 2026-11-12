---
tipo: arquitectura
estado: pendiente
adr: ["[[ADR-020 Hosting y almacenamiento]]"]
---

# Almacenamiento de archivos

> [!todo] Pendiente
> El equipo dejó pendiente dónde se guardan los archivos ([[ADR-020 Hosting y almacenamiento]]).

## Lo que debe cumplir cualquier opción
- **Persistente**: no se pierde al reiniciar ni al redesplegar. E7 reportó archivos perdidos al reiniciar el servidor de Moodle.
- **Respaldado** aparte de la base de datos ([[RNF04]]).
- **Sin estado en la app**, para poder escalar `web` horizontalmente ([[RNF06]]).
- Descarga con **URL firmada temporal**; nunca pública.

## Diseño independiente del proveedor
- Todo el código usa `Storage::disk('archivos')`. Cambiar de proveedor es cambiar la configuración, no el código.
- Rutas: `cursos/{seccion}/recursos/{uuid}`, `entregas/{tarea}/{uuid}`, `importaciones/{uuid}`, `reportes/{uuid}`.

## Opciones
| Opción | A favor | En contra |
|---|---|---|
| MinIO (S3) autoalojado | Compatible con S3, sin costo, respaldable | Otro servicio que operar |
| S3 en la nube (R2, S3) | Sin operación, alta durabilidad | Costo mensual; datos fuera de la UES |
| Google Drive institucional | Usa la cuota de Workspace | API lenta, cuotas, URL no firmadas |
| Volumen local | Lo más simple | No escala horizontalmente; el respaldo depende del servidor |
