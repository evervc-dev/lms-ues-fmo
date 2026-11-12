---
tipo: arquitectura
estado: revisado
adr: ["[[ADR-017 Código modular por dominio]]"]
rnf: ["[[RNF06]]", "[[RNF07]]"]
---

# Estructura del código

Esta estructura ya está creada en el repositorio, con un `.gitkeep` en cada carpeta vacía. Cada uno de los 9 módulos tiene `Models`, `Services`, `Livewire`, `Jobs`, **`Events`**, **`Listeners`** y **`Notifications`**.

El código se organiza **por módulo de dominio** ([[ADR-017 Código modular por dominio]]): un módulo por cada nota de `05 Módulos/`.

```
app/
├── Modules/
│   ├── Identidad/            → [[Identidad y acceso]]
│   │   ├── Models/           User, StudentProfile, StudentDevice, LoginHistory (inglés, ADR-031)
│   │   ├── Services/         GoogleLoginService, DispositivoService
│   │   ├── Livewire/         clases de los componentes (clase + vista); validan el formato en la propia clase
│   │   ├── Jobs/             ImportarUsuariosJob
│   │   ├── Events/           eventos de dominio (p. ej. AsistenciaRegistrada)
│   │   ├── Listeners/
│   │   ├── Notifications/    notificaciones de Laravel del módulo (database, broadcast, mail)
│   │   └── routes.php        (se agrega con el ServiceProvider del módulo)
│   ├── Academica/            → [[Gestión académica]]
│   ├── Contenidos/           → [[Contenidos]]
│   ├── Evaluaciones/         → [[Actividades y evaluaciones]]
│   ├── Calificaciones/       → [[Calificaciones y progreso]]
│   ├── Asistencia/           → [[Asistencia]]
│   ├── Comunicacion/         → [[Comunicación y notificaciones]]
│   ├── Reportes/             → [[Dashboards y reportes]]
│   └── Auditoria/            → [[Auditoría]]
├── Support/                  Transversal: Alcance (autorización), DbContext (transacción + actor de auditoría), DbError (errores SQL a español), Database/Adaptive (migraciones SQLite/PostgreSQL), Navegacion (menú unificado y cabecera, ADR-034), Geo, Redondeo
└── Providers/                Un ServiceProvider por módulo (p. ej. IdentidadServiceProvider) carga app/Modules/<Modulo>/routes.php y registra sus componentes Livewire
config/navegacion.php         Definición del menú por grupos, roles y permisos ([[Layout base]])
resources/views/
├── layouts/                  Layouts guest, app, exam y projection (`<x-layouts::app>`; [[Layout base]])
├── components/ui/            icon, button, modal, accordion, table, field, input, select, textarea, card, badge, alert, breadcrumbs, empty-state, toasts (Tailwind propio)
├── errors/                   403, 404, 419, 429, 500 y 503 en español
└── modules/<modulo>/         vistas Blade de los componentes del módulo, en minúsculas
resources/ayuda/              Manuales para /ayuda ([[Ayuda en la app]])
database/migrations/          Globales (Laravel exige una sola carpeta; los nombres llevan el prefijo del módulo)
database/sql/pgsql/           Capa solo de PostgreSQL (ADR-033): 00 extensiones, 01 EXCLUDE, 02 auditoría, 03 inmutabilidad, 04 RLS, 05 vistas y particiones, 99 reversa
tests/
├── Unit/<Modulo>/  Feature/<Modulo>/  Browser/  Arch/
└── load/                     Scripts k6
```

## Componentes Livewire: clase + vista
Livewire 4 crea por defecto componentes de un solo archivo. En este proyecto **siempre son de clase + vista** (`config/livewire.php` → `make_command.type = 'class'`, sin ⚡):
- **Clase:** `app/Modules/<Modulo>/Livewire/SesionQr.php`. Tiene las propiedades, la validación (`#[Validate]` / `$this->validate()`) y llama al Service.
- **Vista:** `resources/views/modules/<modulo>/sesion-qr.blade.php`, solo Blade y Tailwind.
- **Registro:** en el ServiceProvider del módulo, `Livewire::component('asistencia.sesion-qr', SesionQr::class)`. Se crea en H0.
- `php artisan make:livewire` genera la clase y la vista en `app/Livewire` y `resources/views/livewire`; después se mueven al módulo.

## Patrones (RNF07)
| Patrón | Dónde | Ejemplo |
|---|---|---|
| **Service Layer** | Toda regla de negocio y las operaciones de varios pasos, dentro de `DbContext::run()` ([[ADR-027 Base de datos híbrida]]) | `AttendanceQrService::register()` |
| **Repository** | Solo donde haya consultas complejas o reutilizadas | `AttendanceReportRepository` |
| **Observer** | Finalización automática, recálculo de notas y alertas; la auditoría la hacen triggers de PostgreSQL | `AttendanceRecordObserver` dispara la alerta de inasistencias |
| **Alcance en el Service** | Autorización: permiso de spatie + filtro por alcance ([[ADR-026 Autorización sin Policies]]) | `$this->alcance->exigir($usuario, 'contenidos.ver')->aplicar($query, $usuario)` |
| **Gateway** | Servicios externos, reemplazables en las pruebas | `CalendarGateway`, `MeetReportParser` |

## Reglas de arquitectura (Pest `arch()`)
- Un módulo usa a otro solo por sus Services o Events, nunca por sus modelos internos.
- Livewire no llama a Eloquent para escribir: delega en un Service.
- No hay `env()` fuera de `config/`.
