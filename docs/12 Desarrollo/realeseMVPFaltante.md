---
tipo: desarrollo
estado: en-curso
actualizado: 2026-11-01
corte: 2026-11-01
rama: test
entrega: 2026-11-25
rf: ["[[RF06]]", "[[RF10]]", "[[RF18]]", "[[RF21]]", "[[RF24]]"]
adr: ["[[ADR-018 Calendar con OAuth del docente]]", "[[ADR-020 Hosting y almacenamiento]]", "[[ADR-061 Verificación local sin GitHub Actions]]", "[[ADR-068 Calendario de evaluaciones por ámbito]]"]
rnf: ["[[RNF03]]", "[[RNF09]]", "[[RNF10]]", "[[RNF13]]", "[[RNF14]]"]
tags: [plan, release]
---

# Lo que falta para el release MVP 1.0

Revisión del repositorio contra el vault al **01/11/2026**, rama `test` con los PR #101 a #104 fusionados. Entrega comprometida: **25/11/2026** ([[00 Plan de desarrollo]]). Quedan H2 (3–11/11), H3 (12–17/11), **congelado el 18/11** y H4 de estabilización: **24 días calendario**.

Este documento lista **solo lo que falta**. El avance de las tareas vive en [[OpenProject]]; las decisiones, en la [[Bitácora]]; los planes de Contenidos y Calificaciones, en [[Planes de implementación pendientes]].

> [!important] El cuello de botella no es el código
> De funciones quedan **3 casos de prueba en 2 RF**, y los tres esperan información o k6, no programación. Lo que pone en riesgo el 25/11 son los **cuatro requisitos de release** de la [[Definition of Done]] —cobertura, k6, flujos en navegador y notas de versión— y los bloqueos que no se resuelven programando: **27 ADR sin ratificar**, el **hosting de producción** y los **trámites con TI** (cliente OAuth y SMTP).

## 1. Lo verificado en el repositorio

| Comprobación | Resultado |
|---|---|
| `vendor/bin/pint --test` | ✅ `passed` |
| `vendor/bin/phpstan analyse --level=5` | ✅ `No errors`, con **118 entradas** en `phpstan-baseline.neon` |
| `php artisan test --parallel` (Unit, Feature, Arch) en SQLite | ✅ **1095 pasan, 34 saltadas** (01/11, con #101 a #104 fusionados) |
| La misma suite en **PostgreSQL** | ⚠️ Última corrida el 31/10 (PostgreSQL 18 local, 4 procesos: 1040 pasan). **Falta repetirla** con lo fusionado desde entonces |
| Pruebas de navegador | QR, examen (sin verificar el 01/11) y reporte de Meet (✅ 01/11) |
| Vistas de `config/vistas.php` con componente real | ✅ **Todas**: `perfil.google-calendar` se hizo el 01/11 |
| Pruebas definidas | **917** bloques `it/test` en 73 archivos |
| Casos del vault | **129 `pasa` · 107 `automatizado` · 3 `pendiente`** (+ 2 `sustituido`, de 241) |
| RF | **32 `implementado`** · 2 `en-desarrollo` ([[RF10]], [[RF24]]) · 1 `descartado` ([[RF02]]), de 35 |
| Permisos de la [[Matriz RBAC]] sin uso en el código | Ninguno: `progreso.ver` lo usa [[RF18]] desde el 01/11 |

## 2. ✅ Fusionado en `test` el 01/11

| PR | Qué trae |
|---|---|
| **#101** [[RF18]] (OP#441) | Progreso por unidad y reglas de finalización ([[ADR-067 Formato de la regla de finalización de un recurso]]); cierra CP-RF18-02 |
| **#102** [[RF21]] (OP#453) | Google Calendar real con el OAuth del docente, solo @ues.edu.sv; borrado del evento al cancelar la clase; flujo de navegador 5 |
| **#103** [[RNF09]] | Integraciones de solo lectura con la configuración del `.env` |
| **#104** [[RF06]] (OP#393) | Calendario de evaluaciones en el calendario del Administrador ([[ADR-068 Calendario de evaluaciones por ámbito]]) |

Con los cuatro quedan **32 de 35 RF** implementados. Siguiente paso: regresión en **PostgreSQL 17** con 4 procesos y PR de `test` a `main`.

## 3. Funciones que faltan

| RF | Casos | Qué falta | Qué lo frena | Hito |
|---|---|---|---|---|
| [[RF10]] Oferta académica | 01, 02 | Egresados, períodos históricos y la fórmula oficial de proyección; hoy la pantalla avisa que es provisional | **Información**: la fórmula y los datos los da la universidad. Si no llegan antes del 18/11, declararlo fuera del 1.0 | H3 |
| [[RF24]] Paneles | 02 | Medir que el panel carga en menos de 3 s ([[RNF03]]) | **k6** (sección 4.2) | H3 |
| ~~[[RF06]] Calendario por ámbito~~ | ~~09~~ | ✅ **Hecho el 01/11**: permiso `periodos.calendario.gestionar` (G/D/C), `department_id` y `career_id` en `period_calendar_events` con `CHECK` de exclusión, y la página «Calendario de evaluaciones». Cada quien sincroniza en **su propio** calendario e invita solo a su ámbito ([[ADR-068 Calendario de evaluaciones por ámbito]], ampliada). CP-RF06-09 nuevo | — | H2 |
| RF36 Perfiles contextuales | — | El código y la prueba existen (`PerfilContextualService`, `PerfilContextualTest`), pero **no hay especificación**: faltan `RF36.md`, `CP-RF36.md` y `ADR-056 Visibilidad contextual de perfiles` | Solo documentación | H2 |

## 4. Requisitos de release que no están listos

La [[Definition of Done]] exige cuatro cosas extra para un release a `main`. **Ninguna de las cuatro está hecha.**

### 4.1 La cobertura no se puede medir
`php -m` no trae **ni pcov ni xdebug** (verificado el 01/11), así que el tercer paso de `composer check` (`pest --coverage --min=70`) no puede completarse. Sin CI ([[ADR-061 Verificación local sin GitHub Actions]]) no hay otro sitio donde medirla. **Es el único punto que puede invalidar todo lo demás: instalar pcov primero.**

### 4.2 k6 no existe
`tests/load/` tiene solo `.gitkeep`. Los cuatro escenarios están especificados en [[Pruebas de carga]] —500 usuarios ([[RNF03]]), 100 exámenes simultáneos ([[RNF10]]), pico de QR y notificación masiva ([[RNF12]])— sin un solo script. Es además el único modo de cerrar CP-RF24-02.

### 4.3 Flujos críticos en navegador: 3 de 5
- [ ] **Flujo 1** · Inicio de sesión con Google: dominio válido, rechazado y usuario sin registrar ([[RF01]])
- [x] Flujo 2 · Asistencia con QR ([[RF20]]) — `tests/Browser/AsistenciaQrTest.php` (pasa el 01/11)
- [ ] **Flujo 3** · Entrega de tarea: a tiempo, en gracia y rechazada ([[RF15]])
- [x] Flujo 4 · Cuestionario ([[RF16]]) — `FlujoExamenTest.php`, `ResolverExamenTest.php`; **sin el corte de red y la reconexión** que pide [[RNF10]]. Las tres pruebas están **en inglés y sin su ID `CP-RFxx`**, y `ResolverExamenTest` **se colgaba sin salida** (31/10; no se volvió a probar)
- [x] Flujo 5 · Reporte de Meet con filas sin asociar ([[RF21]]) — `tests/Browser/ReporteMeetTest.php` (01/11), desde la vista previa: Pest Browser 4.3 no pasa archivos *multipart* a la app (`LaravelHttpServer`: «@TODO files»), así que la lectura del archivo la cubre CP-RF21-02

> [!warning] Subidas de archivos en navegador
> Ninguna prueba de navegador puede subir un archivo con Pest Browser 4.3. El flujo 3 (entrega de tarea) tendrá que empezar con la entrega ya cargada por el servicio, como el flujo 5, o esperar una versión del plugin que lo soporte.

### 4.4 Sin etiqueta semver ni notas de versión
`git tag` está vacío y el repositorio no tiene CHANGELOG (verificado el 01/11).

## 5. Bloqueos que no se resuelven programando

| # | Bloqueo | Por qué importa |
|---|---|---|
| 1 | **27 ADR en `pendiente`** de aceptar | ADR-020, 036, 039 a 044, 047, 049, 050, 052 a 055 y 057 a 068. Varias sostienen reglas **ya implementadas** (por ejemplo [[ADR-052 Prerrequisitos exigidos al matricular]], [[ADR-054 Topes de la carga académica]], [[ADR-058 Fórmula del riesgo académico]], [[ADR-062 Notas y entregas en un cambio de grupo]]). El release iría sobre decisiones sin ratificar: hay que convocar la votación |
| 2 | **Hosting de producción sin decidir** ([[ADR-020 Hosting y almacenamiento]]) | Staging existe, pero producción no tiene destino. Bloquea `Storage::disk('archivos')`, los respaldos ([[RNF04]]) y la disponibilidad ([[RNF03]]) |
| 3 | **Trámites con TI de la UES** | **Cliente OAuth de producción**: pantalla Interna, Calendar API y las URI de producción y staging (`/auth/google/callback` y `/perfil/google-calendar/callback`); hoy se usa el cliente del equipo. **SMTP institucional**: sin él no sale ningún correo. **Reporte de asistencia de Meet**: confirmar que la edición de Workspace lo genera ([[ADR-003 Asistencia virtual por reporte de Meet]]) |
| 4 | **Los 14 RNF están en `borrador`** | Ninguno aceptado ni verificado. [[RNF09]] nombra Drive: el único uso en los RF es incrustar enlaces ([[RF12]], automatizado); [[Almacenamiento de archivos]] descartó Drive como almacenamiento |
| 5 | ~~**Los 8 manuales en `esqueleto`**~~ | ✅ **Redactados el 01/11** (`revisado`): los 6 de rol, la [[Guía de bienvenida]] y [[Despliegue]], con los nombres reales de menús y botones y una tabla de problemas frecuentes. La Ayuda ya no muestra notas internas (A-03, A-05). **Faltan las capturas** (`capturas: pendientes`) |
| 6 | ~~**H-01 sin reproducir**~~ | ✅ **Corregido el 01/11**: era el error de Livewire por falta de memoria al armar un PDF de 822 filas (262 MB contra 256 MB en staging). El PDF se corta en 500 filas con un aviso que manda al Excel |
| 7 | **Pruebas que solo corren en PostgreSQL** | 34 se saltan en SQLite (triggers, RLS, CHECK, EXCLUDE). Hay que correr la suite en PostgreSQL 17 con 4 procesos antes de cada `main` ([[RNF14]]); las de RLS, con un rol no superusuario (receta en la [[Estrategia de pruebas]]) |
| 8 | **[[OpenProject]] sin actualizar** | Sin `OP_TOKEN` en el entorno no se registraron los estados de OP#441, OP#453 y OP#393, los PR #101 a #104 fusionados ni los defectos de estas sesiones |

## 6. Documentación por corregir (sin escribir código)

- **RF36, CP-RF36 y ADR-056** (sección 3): es la única funcionalidad del sistema sin especificación.
- **107 casos en `automatizado`** esperan la corrida verificada en PostgreSQL para pasar a `pasa`.
- El [[Roadmap]] tiene **corte del 22/10** (`commit 5e7beed`) y da cifras muy desfasadas.
- Decidir si la tabla `integrations` sigue en el ER: desde el PR #103 nadie la usa y `DemoAcademicSeeder` todavía la siembra.

## 7. Orden sugerido

### Esta semana, antes de que arranque H2 el 3/11
1. **Instalar pcov** y correr `composer check` completo. Sigue siendo lo primero.
2. ~~Fusionar #101 a #104~~ → **hecho el 01/11**. Falta la regresión en **PostgreSQL 17** con 4 procesos y el PR de `test` a `main`.
3. Convocar la **votación de las 27 ADR pendientes**.
4. **Pedir a TI** el cliente OAuth de producción, el SMTP y la confirmación del reporte de Meet.
5. Decidir el **calendario por ámbito** (sección 3).

### H2 · 3–11/11
Flujos de navegador **1 y 3**; arreglar el cuelgue de `ResolverExamenTest` y poner los IDs `CP-RFxx` a las pruebas del examen. Escribir **RF36, CP-RF36 y ADR-056**. El calendario por ámbito, si se aprueba.

### H3 · 12–17/11
Los **4 escenarios de k6** (cierran CP-RF24-02). [[RF10]] si la universidad entrega la fórmula; si no, declararlo **fuera del 1.0** antes del congelamiento.

### H4 · 18–24/11
Regresión final en PostgreSQL 17 (los `automatizado` → `pasa`), k6 en staging, los **8 manuales** con capturas, notas de versión y la etiqueta `v1.0.0`.

## 8. Google Calendar: ya no es un riesgo de código

El 31/10 era el riesgo singular del release: no existía ni una línea. El 01/11 quedó hecho y fusionado en `test` (PR #102 y #104):
- El docente conecta su cuenta @ues.edu.sv en Mi perfil › Google Calendar; sus clases virtuales crean el evento con Meet e invitan a los matriculados; al cancelarse el día ([[RF32]]) el evento se borra ([[ADR-018 Calendar con OAuth del docente]]).
- El Administrador sincroniza el calendario de evaluaciones del período ([[ADR-068 Calendario de evaluaciones por ámbito]]).
- **Probado contra Google real el 01/11** con `php artisan google:calendar-prueba` (evento con Meet creado).

Lo que queda: el **cliente OAuth de TI** (sección 5), probar la sincronización del período contra Google real en staging y vigilar el **límite de invitados por evento** de Google en un período grande.

## 9. Cerrado desde el 31/10

Todo fusionado en `test`. Detalle en la [[Bitácora]] y en [[Respuesta al informe de QA (sesión 5, roles Jefe y Soporte)]].
- **31/10:** CP-RF04-22, CP-RF07-05, CP-RF07-07, CP-RF08-06, CP-RF25-04 y CP-RF25-05 (solo faltaba la prueba); CP-RF09-08 con [[ADR-062 Notas y entregas en un cambio de grupo]]; CP-RF09-05 → `sustituido` por [[ADR-037 Jerarquía de creación y asignación de roles]]; CP-RF15-05, CP-RF19-02 y CP-RF32-02; los hallazgos de la QA del Jefe y Soporte, salvo la reactivación de una matrícula retirada ([[ADR-063 Reactivar una matrícula retirada por error]], pendiente y sin implementar); el cuelgue de `composer test` por la suite de navegador.
- **01/11:** [[RF13]], [[RF14]] ([[ADR-066 Evaluaciones sin programar al reutilizar]]), [[RF18]] y [[RF33]] → `implementado`; [[RF21]] con Google Calendar real y el flujo de navegador 5; CP-RF06-04 ([[RF06]] → `implementado`); Integraciones de solo lectura.

## 10. No verificado en esta revisión

- **PostgreSQL:** no se corrió el 01/11 (sección 1).
- **Navegador:** solo QR y reporte de Meet; `FlujoExamenTest` y `ResolverExamenTest` no se corrieron.
- **Cobertura:** no medible sin pcov (sección 4.1).
- **[[OpenProject]]:** sin `OP_TOKEN`, no se leyó el avance oficial ni los defectos abiertos.
