---
tipo: adr
id: ADR-055
estado: pendiente
fecha: 2026-10-28
afecta: ["[[RF26]]", "[[RF20]]", "[[ADR-046 Lector QR en la página y rotación por polling]]", "[[Plan de implementación]]", "[[Stack y paquetes]]"]
fuentes: ["Decisión del usuario (28/10/2026)", "docs/13 Layout/Plan de implementación.md §7"]
---

# ADR-055 · Leaflet y OpenStreetMap para las áreas de asistencia

> [!warning] Pendiente de aceptar
> **Actualiza el punto 4 de [[ADR-046 Lector QR en la página y rotación por polling]]**, que rechazaba como `out_of_range` las áreas por polígono porque todavía no existían.

## Contexto
[[RF26]] pide definir las áreas de asistencia «por centro y radio o por polígono». La base ya tenía `attendance_areas.area_type` (`circle`, `polygon`) y `polygon jsonb` desde el modelo v2, pero la pantalla solo aceptaba centro y radio y la validación del QR filtraba `area_type = 'circle'`. Dibujar un polígono escribiendo coordenadas a mano es inusable, y el [[Plan de implementación]] (§7) dejaba el mapa como dependencia pendiente de aprobar, con Leaflet y OpenStreetMap como opción sugerida. Era lo único de H1 bloqueado por una decisión externa.

## Decisión
*Aprobada por el usuario el 28/10/2026.*

1. **Leaflet 1.9.4** (`bun`, versión exacta) con los mapas de **OpenStreetMap**, sin plugins de dibujo. El polígono se dibuja con clics: cada clic agrega un vértice; «Deshacer» y «Limpiar» lo corrigen. Leaflet se carga con `import()` dinámico solo al abrir el formulario de áreas.
2. **El servidor manda.** El mapa solo ayuda a dibujar: `AsistenciaService::guardarPoligono()` valida de 3 a 50 vértices, coordenadas en rango y lados que no se crucen, igual que si los vértices se hubieran escrito a mano. `GeocercaService` hace la geometría (punto dentro, distancia al borde y centroide) en un plano local en metros.
3. **El margen del GPS va dentro del dibujo.** El círculo lo lleva en el radio; el polígono no tiene otra columna, así que el Administrador lo dibuja con unos metros de holgura y la pantalla se lo indica. No se agrega columna (el ER no la tiene).
4. **Al marcar con QR** ([[RF20]]) el polígono cuenta como cualquier área activa: dentro o en el borde, distancia 0; fuera, se guarda la distancia al borde más cercano.
5. **CHECK `attendance_areas_shape`** en PostgreSQL: un círculo lleva centro y radio; un polígono, al menos 3 vértices. Se crea `NOT VALID`, para que una fila vieja no tumbe el despliegue.

## Alternativas consideradas
- **Sin mapa (pegar vértices o un GeoJSON):** sin dependencia nueva, pero incómodo y propenso a errores.
- **Leaflet-Draw:** dibujo completo, pero sin cambios desde 2018.
- **Leaflet-Geoman:** edición de vértices arrastrándolos, pero pesado para dibujar un aula o un edificio.
- **Mover el polígono a H2:** dejaba [[RF26]] sin cerrar en H1.

## Consecuencias
- Dependencia nueva: `leaflet` 1.9.4 (bun). Unos 43 kB comprimidos, cargados solo en «Áreas de asistencia».
- Los mapas vienen de `tile.openstreetmap.org`, cuya política de uso pide atribución (la muestra Leaflet) y tráfico moderado: basta para una pantalla que solo usa el Administrador. Sin internet el mapa no carga, pero el círculo se puede definir escribiendo las coordenadas.
