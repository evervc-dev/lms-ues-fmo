---
tipo: adr
id: ADR-048
estado: aceptada
fecha: 2026-10-24
aceptada: 2026-10-25
afecta: ["[[RF21]]", "[[RF04]]", "[[ADR-016 Paquetes]]", "[[ADR-003 Asistencia virtual por reporte de Meet]]", "[[Flujo Asistencia virtual con reporte de Meet]]"]
fuentes: ["Decisión del usuario (24/10/2026)"]
---

# ADR-048 · Lector propio de CSV y Excel

> [!success] Aceptada el 25/10/2026
> **Supera a [[ADR-016 Paquetes]] en la lectura de hojas de cálculo:** el proyecto **no instala `maatwebsite/excel`** para leer. Lo que ADR-016 decide sobre PDF y sobre los demás paquetes sigue vigente.
>
> Alcance ampliado el mismo día: el lector no es solo del reporte de Meet. Vale para **cualquier** entrada de hoja de cálculo, y la primera que lo usa además de [[RF21]] es la importación de estudiantes de [[RF04]].

## Contexto
[[RF21]] pide leer el reporte de asistencia de Meet en **CSV o Excel** (la hoja de Google Sheets exportada). [[ADR-016 Paquetes]] eligió `maatwebsite/excel`, pero no está instalado: hoy el proyecto no tiene ninguna librería de hojas de cálculo. El reporte de Meet es una tabla simple: encabezados y filas de texto, sin fórmulas ni varias hojas.

## Opciones
- **A. Lector propio** en `App\Support\HojaCalculo`: CSV con `fgetcsv` (como `ImportacionService`) y `.xlsx` con `ZipArchive` + `XMLReader` (la primera hoja, con sus cadenas compartidas). No agrega dependencias.
- **B. Instalar `maatwebsite/excel`** como dice [[ADR-016 Paquetes]]. Trae PhpSpreadsheet, que es pesado, solo para leer una tabla.
- **C. Solo CSV.** Contradice el criterio «CSV o Excel» de [[RF21]].

## Decisión
**A.** *Decidido por el usuario el 24/10/2026, al planificar la entrega 1 de RF21.*

## Consecuencias
- Solo se lee `.xlsx` (no `.xls` antiguo ni `.ods`). Las fechas y las duraciones que Excel guarda como número de serie se convierten en `ReporteMeetService`.
- Las exportaciones a Excel pendientes ([[RF17]], [[RF33]], [[RF06]]) no quedan resueltas por este ADR: si necesitan Excel con formato, se vuelve a evaluar `maatwebsite/excel`.
- Si se acepta, [[ADR-016 Paquetes]] debe indicar que la **lectura** de Excel no usa `maatwebsite/excel`.

## La importación de estudiantes ([[RF04]])
*Resuelto el 25/10/2026, al aceptar esta ADR.* `ImportacionService::leer()` usa `HojaCalculo::leer()` y la pantalla admite `.csv` y `.xlsx`: el Coordinador sube el libro tal como lo guarda Excel, sin convertirlo a mano ([[CP-RF04]]-21). La plantilla descargable sigue siendo **CSV**, porque `HojaCalculo` solo lee y la pantalla acepta las dos cosas de todos modos ([[CP-RF04]]-20).

## Escribir es otro problema
Esta ADR decide **leer**. Para **escribir** `.xlsx` y PDF ([[RF17]], [[RF33]], [[RF06]]) ver [[ADR-049 Exportación a Excel y PDF]].
