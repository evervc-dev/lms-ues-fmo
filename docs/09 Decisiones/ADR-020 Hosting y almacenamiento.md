---
tipo: adr
id: ADR-020
estado: pendiente
fecha: 2026-10-10
afecta: ["[[Almacenamiento de archivos]]", "[[Despliegue]]", "[[Respaldos y recuperación]]", "[[RNF03]]", "[[RNF04]]"]
fuentes: []
---

# ADR-020 · ¿Dónde se hospeda y dónde se guardan los archivos?

## Contexto
El equipo aún no sabe dónde se hospedará el LMS. El almacenamiento de archivos y el destino de los respaldos dependen de eso.

## Opciones de hosting
- Servidor de la UES con Coolify, gestionado por TI.
- Infraestructura propia del equipo con Coolify.
- VPS o nube pagada por el proyecto.

## Opciones de archivos
Ver [[Almacenamiento de archivos]].

## Restricciones
- Dominio `ues.edu.sv` con TLS ([[RNF01]]).
- 99 % de disponibilidad de 6:00 a 22:00 ([[RNF03]]).
- Respaldo fuera del servidor ([[RNF04]]).
- La app ya es independiente del proveedor: contenedores y `Storage::disk('archivos')`.

## Decisión
_Pendiente del equipo._
