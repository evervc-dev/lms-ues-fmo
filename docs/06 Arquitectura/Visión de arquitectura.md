---
tipo: arquitectura
estado: revisado
adr: ["[[ADR-001 Stack Laravel y Livewire]]", "[[ADR-015 Plataforma base]]", "[[ADR-017 Código modular por dominio]]"]
---

# Visión de arquitectura

Un **monolito modular Laravel 12 + Livewire 4** sin API pública ([[ADR-001 Stack Laravel y Livewire]]), con PostgreSQL, Redis + Horizon y Reverb, integrado con Google Workspace.

## Contexto (C4 nivel 1)
```mermaid
flowchart LR
  E((Estudiante)) & D((Docente)) & C((Coordinador / Jefe)) & A((Admin / Soporte)) --> LMS[Plataforma LMS<br/>UES FMO]
  LMS -->|"OAuth: inicio de sesión @ues.edu.sv"| GI[Google Identity]
  LMS -->|"OAuth del docente: eventos con Meet"| GC[Google Calendar]
  LMS -->|SMTP| ML[Correo institucional]
  D -.->|Descarga el reporte| GM[Google Meet]
  D -.->|Sube CSV/Excel| LMS
```

## Contenedores (C4 nivel 2)
```mermaid
flowchart TB
  subgraph Usuario
    B[Navegador / teléfono<br/>Livewire + Alpine + Tailwind]
  end
  subgraph Servidor["Hosting (pendiente: ADR-020)"]
    W[web<br/>nginx + PHP-FPM 8.4<br/>Laravel 12]
    H[horizon<br/>workers de cola]
    S[scheduler<br/>schedule:work]
    RV[reverb<br/>WebSockets]
    PG[(PostgreSQL 17<br/>datos + sesiones)]
    RD[(Redis 7<br/>caché + colas)]
    FS[(Archivos<br/>pendiente: ADR-020)]
  end
  BK[(Destino externo<br/>de respaldos)]
  B -->|HTTPS| W
  B <-->|WSS| RV
  W --> PG & RD & FS
  H --> PG & RD & FS
  S --> RD
  W -->|eventos broadcast| RV
  H -->|spatie/laravel-backup| BK
```

| Contenedor | Responsabilidad | Escala |
|---|---|---|
| **web** | HTTP, componentes Livewire, Services (con autorización) | Horizontal: sin estado ([[RNF06]]) |
| **horizon** | Notificaciones, importaciones, reportes, llamadas a Google | Por supervisor ([[Colas, programador y tiempo real]]) |
| **scheduler** | Tareas periódicas | Una sola instancia (`onOneServer`) |
| **reverb** | Notificaciones en tiempo real ([[ADR-010 Notificaciones en tiempo real]]) | Con Redis como escalado |
| **PostgreSQL** | Datos, sesiones, auditoría | Vertical, más respaldos |
| **Redis** | Caché, colas, broadcasting | — |

## Notas de esta sección
[[Stack y paquetes]] · [[Estructura del código]] · [[Integraciones con Google]] · [[Seguridad]] · [[Colas, programador y tiempo real]] · [[Almacenamiento de archivos]] · [[Entornos]] · [[Respaldos y recuperación]] · [[Capacidad y rendimiento]] · [[Observabilidad]]

## Decisiones pendientes
- **Hosting y almacenamiento de archivos:** [[ADR-020 Hosting y almacenamiento]].
- **Monitoreo:** [[ADR-021 Monitoreo]].
