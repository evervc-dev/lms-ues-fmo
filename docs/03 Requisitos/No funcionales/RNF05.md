---
tipo: rnf
id: RNF05
nombre: Usabilidad y accesibilidad
aliases: ["Usabilidad y accesibilidad"]
categoria: Usabilidad
prioridad: Alta
adr: []
estado: borrador
fuente: Requerimientos.pdf p.18
---

# RNF05 · Usabilidad y accesibilidad

## Descripción
Interfaz intuitiva y responsive (mobile-first). Funciona en Chrome, Firefox, Edge y Safari modernos. Sigue WCAG 2.1 nivel AA. Los mensajes de error son claros y en español. Hay ayuda contextual en los formularios complejos.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Accesibilidad | WCAG 2.1 AA; Lighthouse Accessibility ≥ 90 | Lighthouse y axe |
| Responsive | Usable desde 360 px de ancho | Pruebas en móvil |
| Navegadores | Últimas 2 versiones de Chrome, Firefox, Edge y Safari | Pruebas manuales |

## Implicaciones técnicas
- Contraste, foco visible, etiquetas en formularios y navegación con teclado.

## Requisitos funcionales relacionados
```base
filters:
  and:
    - tipo == "rf"
    - file.hasLink(this.file)
views:
  - type: table
    name: RF relacionados
    order: [file.name, nombre, prioridad, modulo]
```
