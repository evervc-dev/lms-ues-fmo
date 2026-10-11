---
tipo: adr
id: ADR-001
estado: aceptada
fecha: 2026-10-10
afecta: ["[[RNF06]]", "[[RNF07]]", "[[RNF09]]"]
fuentes: [Roles y permisos.pdf, Requerimientos.pdf]
---

# ADR-001 · Stack Laravel + Livewire

## Contexto
Varios documentos asumen Laravel: la matriz de roles nombra `spatie/laravel-permission`, y [[RNF07]] exige PSR, migraciones de Laravel y PHPUnit. [[RNF09]] define una aplicación monolítica que **no expone API pública** y que solo consume las APIs de Google Workspace.

## Decisión
Será un **monolito Laravel con Livewire** para la interfaz, con Blade y Alpine.js.

## Consecuencias
- No se diseña una API REST pública. Las integraciones son salientes, hacia Google.
- La interactividad (acordeones, drag and drop, autoguardado de exámenes) se resuelve con Livewire y Alpine.
- El tiempo real que pide [[RNF12]] se resuelve con Laravel Reverb ([[ADR-010 Notificaciones en tiempo real]]).
- La autorización usa spatie/laravel-permission y el alcance se aplica en los Services, sin Policies ([[ADR-026 Autorización sin Policies]]). Ver [[Matriz RBAC]].
