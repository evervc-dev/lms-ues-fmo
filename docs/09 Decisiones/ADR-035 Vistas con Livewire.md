---
tipo: adr
id: ADR-035
estado: aceptada
fecha: 2026-10-11
afecta: ["[[Plan de implementación]]", "[[Mapa de vistas]]", "[[Vista de sección]]", "[[Layout base]]"]
fuentes: []
---

# ADR-035 · Vistas con Livewire: navegación, esqueleto y orden

## Contexto
Hay 68 vistas documentadas ([[Mapa de vistas]]) y 31 maquetas ([[Maquetas]]), y 5 equipos que las construyen en paralelo entre H1 y H3. Hacía falta definir cómo se navega, cómo se reparten las vistas de una sección y cómo se evita que cada equipo improvise rutas y permisos.

## Decisión
*Decidido por el equipo el 11/10/2026:*
1. **Cada vista es un componente Livewire de página completa** (clase + vista) en su módulo. La lógica va en el Service, y los componentes visuales son los `x-ui.*` en Blade.
2. **Navegación con `wire:navigate`:**
   - el menú lateral persiste con `@persist`;
   - la opción activa se marca con `wire:current`;
   - la barra de progreso va en rojo UES.
3. **Esqueleto navegable desde H0:**
   - un registro único (`config/vistas.php`) declara las 68 rutas con su permiso, RF, OP y maqueta;
   - las que aún no existen muestran «En construcción» con su maqueta;
   - el registro alimenta el menú y una prueba de la matriz ruta × rol.
4. **Vista de sección con una página por pestaña**, cada una con su URL. La cabecera y las pestañas son componentes Blade compartidos.
5. **Construcción por RF, de punta a punta** (Back + Front + Test), en el orden del [[Roadmap]].

## Opciones descartadas
- **Recarga completa:** más simple, pero más lenta en el teléfono, y el menú parpadea.
- **Que cada equipo cree su ruta:** el menú quedaría lleno de «Pronto» y no se probarían los permisos de punta a punta hasta H3.
- **Una sección con pestañas internas:** cuatro equipos editarían el mismo componente.
- **Todas las pantallas primero, con datos falsos:** obliga a rehacer trabajo al conectarlas.

## Consecuencias
- Cada equipo, al implementar una vista, cambia su ruta provisional por `Route::livewire()` con **el mismo nombre**. La prueba de la matriz sigue valiendo.
- Los scripts que dependen de la página (cámara, mapas, gráficos) deben reiniciarse en el evento `livewire:navigated`.
- Fase 0 de 42 h para el Equipo 1, detallada en [[Plan de implementación]].
