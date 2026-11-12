---
tipo: arquitectura
estado: revisado
rnf: ["[[RNF04]]"]
adr: ["[[ADR-016 Paquetes]]"]
---

# Respaldos y recuperación

Con **spatie/laravel-backup**, independiente del hosting.

| Qué | Cuándo | Retención | Destino |
|---|---|---|---|
| Volcado de PostgreSQL (`pg_dump`, comprimido y cifrado) | Diario 01:30 | **30 días** | Disco externo al servidor (pendiente con el hosting) |
| Archivos subidos | Diario 01:30, o el versionado del almacenamiento | 30 días | Depende de [[Almacenamiento de archivos]] |
| `.env` y secretos | En cada cambio | — | Gestor de secretos del equipo, **no** en el respaldo |

- `backup:monitor` alerta por correo si el último respaldo tiene más de 26 horas o el destino supera su cuota.
- **RPO < 24 h**: el respaldo diario lo cumple.
- **RTO < 4 h**: hay que demostrarlo con un **simulacro trimestral** documentado.

## Procedimiento de restauración (resumen)
1. Levantar la infraestructura con la misma versión de la app (etiqueta del release).
2. Restaurar el volcado de PostgreSQL más reciente válido.
3. Restaurar los archivos.
4. Restaurar los secretos del `.env`.
5. `php artisan migrate --force` (por si el respaldo es anterior al release).
6. Verificar: inicio de sesión, un curso, notas y la auditoría.
7. Registrar el tiempo total del simulacro.
