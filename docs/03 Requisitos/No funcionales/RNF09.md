---
tipo: rnf
id: RNF09
nombre: Compatibilidad e integración con servicios externos
aliases: ["Compatibilidad e integración con servicios externos"]
categoria: Integración
prioridad: Media-Alta
adr: ["[[ADR-001 Stack Laravel y Livewire]]", "[[ADR-003 Asistencia virtual por reporte de Meet]]", "[[ADR-007 Inicio de sesión con Google]]"]
estado: borrador
fuente: Requerimientos.pdf p.19
---

# RNF09 · Compatibilidad e integración con servicios externos

## Descripción
Compatible con móviles y escritorio en navegadores modernos. Al ser una aplicación monolítica, **no expone una API pública propia**. Consume las APIs oficiales de Google Workspace: inicio de sesión, correo, Drive, Meet y Calendar, con las credenciales y permisos que autorice la institución.

## Métricas verificables
| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Integraciones | Inicio de sesión, Calendar con enlace de Meet, Drive y correo funcionando con la cuenta de la UES | Pruebas de integración en staging |

## Implicaciones técnicas
- Requiere un proyecto de Google Cloud de la UES con OAuth para el inicio de sesión y acceso a Calendar para crear eventos con enlace de Meet. Hay que tramitarlo con TI de la UES.
- Las llamadas a Google van en cola, con reintentos y respetando las cuotas.
- La asistencia virtual **no** usa la API de Meet: se calcula con el reporte que sube el docente ([[ADR-003 Asistencia virtual por reporte de Meet]]).

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
