---
tipo: tecnico
estado: revisado
rnf: ["[[RNF05]]", "[[RNF13]]"]
---

# Ayuda en la app

Decidido el 10/10/2026: los manuales del vault se publican dentro del LMS en la ruta `/ayuda`, como pide [[RNF05]] (ayuda contextual).

## Cómo funciona
1. Las notas de `11 Manuales/` con propiedad `ayuda_ruta` se copian a `resources/ayuda/<slug>.md` del repositorio.
2. Una ruta `GET /ayuda/{slug}` convierte el Markdown en HTML (con `league/commonmark`, incluido en Laravel) y lo muestra en el layout de la app.
3. Cada pantalla tiene un ícono **?** que abre la sección que le corresponde, p. ej. `/ayuda/docente#5-tomar-asistencia-con-qr`.
4. Solo se muestran las ayudas de los roles del usuario.

## Conversión del vault
| En Obsidian | En la app |
|---|---|
| `[[RF20]]` y otros enlaces internos | Se quitan, porque el usuario final no ve requisitos |
| `> [!example] Captura pendiente` | Se reemplaza por la imagen de `public/ayuda/` |
| Bloques ` ```mermaid ` | Se vuelven una **lista numerada**: los pasos de un `flowchart`, los mensajes de un `sequenceDiagram` |
| Avisos `> [!warning]` | Se vuelven una cita con el título en negrita y su emoji |
| Propiedades | Se ignoran, excepto `ayuda_ruta` |

> [!todo] Script de sincronización
> Un comando `php artisan ayuda:sincronizar` (módulo Comunicacion) copia los manuales a `resources/ayuda`. La traducción de los diagramas y los avisos la hace la pantalla al dibujar el manual (`Comunicacion\Livewire\Ayuda`), así que el vault conserva el Mermaid que Obsidian sí sabe dibujar.

> [!note] Por qué no se carga mermaid.js
> Decidido el 25/10/2026: los bloques salían como código crudo en medio del texto. Cargar mermaid.js son unos 2 MB de JavaScript para dos diagramas de dos manuales, y la lista numerada se lee igual de bien, se imprime y la leen los lectores de pantalla.

| Manual | Ruta |
|---|---|
| [[Guía de bienvenida]] | `/ayuda/bienvenida` |
| [[Manual Estudiante]] | `/ayuda/estudiante` |
| [[Manual Docente]] | `/ayuda/docente` |
| [[Manual Coordinador]] | `/ayuda/coordinador` |
| [[Manual Jefe de Departamento]] | `/ayuda/jefe-de-departamento` |
| [[Manual Administrador]] | `/ayuda/administrador` |
| [[Manual Soporte Técnico]] | `/ayuda/soporte-tecnico` |

[[Operación - Cuenta de emergencia]] **no** se publica: no tiene `ayuda_ruta`.
