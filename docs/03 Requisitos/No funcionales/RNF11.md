---
tipo: rnf
id: RNF11
nombre: Localización e idioma
aliases: ["Localización e idioma"]
categoria: Usabilidad
prioridad: Media
adr: []
estado: borrador
fuente: Requerimientos.pdf p.20
---

# RNF11 · Localización e idioma

## Descripción
Interfaz completamente en español. Fechas en formato DD/MM/YYYY con la zona horaria institucional. Números con punto como separador decimal. Textos adaptados al contexto académico.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Idioma | 0 textos en inglés en la interfaz | Revisión |
| Zona horaria | America/El_Salvador | Configuración |

## Implicaciones técnicas
- `APP_LOCALE=es`, `APP_TIMEZONE=America/El_Salvador` y traducciones de validación en `lang/es`.

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
