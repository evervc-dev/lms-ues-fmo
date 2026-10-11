---
tipo: adr
id: ADR-049
estado: pendiente
fecha: 2026-10-25
afecta: ["[[RF17]]", "[[RF33]]", "[[RF06]]", "[[RF25]]", "[[RF05]]", "[[ADR-016 Paquetes]]", "[[ADR-048 Lector propio de hojas para el reporte de Meet]]"]
fuentes: ["Decisión del usuario (25/10/2026)"]
---

# ADR-049 · Exportación a Excel y PDF

> [!warning] Pendiente de aceptar
> **Supera a [[ADR-016 Paquetes]] en la escritura de Excel:** no se instala `maatwebsite/excel`. Confirma su elección de `barryvdh/laravel-dompdf` para PDF y la pone en uso por primera vez.

## Contexto
[[RF17]], [[RF33]] y [[RF06]] piden «exportar a **Excel o PDF**», y [[RF25]] «a Excel, PDF y gráficos». Hasta hoy el sistema solo exportaba **CSV**, en cuatro pantallas y con el mismo bloque copiado cuatro veces. [[Entrevista E3]] y [[Entrevista E7]] piden el PDF con nombre: «descarga de calificaciones en PDF para publicarlas».

[[ADR-016 Paquetes]] eligió `maatwebsite/excel` + `barryvdh/laravel-dompdf`, y **ninguno de los dos estaba instalado**. [[ADR-048 Lector propio de hojas para el reporte de Meet]] ya resolvió **leer** sin librería; esta ADR resuelve **escribir**, que es otro problema: `App\Support\HojaCalculo` no escribe.

## Opciones
- **A. `openspout/openspout` + `barryvdh/laravel-dompdf`.** Openspout escribe `.xlsx` en streaming, sin armar el libro en memoria, y no arrastra PhpSpreadsheet. Dompdf ya estaba elegido en [[ADR-016 Paquetes]].
- **B. `maatwebsite/excel`**, como dice [[ADR-016 Paquetes]]. Trae PhpSpreadsheet: pesado y hambriento de memoria para producir tablas sin fórmulas.
- **C. Escritor propio de `.xlsx`.** Un `.xlsx` es un ZIP con varios XML; leerlo es sencillo, escribirlo con estilos y tipos no. No vale la pena reinventarlo.
- **D. Seguir solo con CSV.** Contradice el criterio de los cuatro RF, y no sirve para lo que pidieron en las entrevistas: un CSV no se imprime ni se pega en la pared.

## Decisión
**A.** *Decidido por el usuario el 25/10/2026.*

`App\Support\Exportacion` concentra los tres formatos con la misma forma de llamarse (`csv()`, `xlsx()`, `pdf()`), y `<x-ui.exportar>` es el botón único de las pantallas. Los Services devuelven `columnas` y `filas` ya resueltas, con su permiso y su alcance aplicados; `Exportacion` no consulta ni autoriza nada.

Los PDF usan la hoja común `<x-exportacion.hoja>`, con el membrete de la facultad, la fecha, quién lo generó y el número de página. Dompdf no corre Tailwind ni JavaScript, así que esa hoja lleva su CSS aparte.

**Formato común del Excel (31/10/2026, a pedido de la QA del rol Jefe):** todo `.xlsx` abre con tres filas de cabecera, con los mismos textos que el membrete del PDF:
1. La facultad.
2. El reporte con su período, su sección o sus filtros, en palabras («Asistencia por sección · Período 2026-II · Carrera ISI»).
3. Quién lo generó y cuándo.

Después van los encabezados de columna en negrita y los datos (`Exportacion::FILAS_DE_CABECERA`). El CSV no lleva cabecera: es el formato para procesar los datos con otro programa.

## Qué pantalla ofrece qué
| Pantalla | Excel | PDF | CSV | Por qué |
|---|:--:|:--:|:--:|---|
| Libro de calificaciones ([[RF17]]) | ✅ | ✅ | ✅ | lo pide el RF y lo pidieron en las entrevistas |
| Desempeño docente ([[RF33]]) | ✅ | ✅ | ✅ | lo pide el RF |
| Período ([[RF06]]) | ✅ | ✅ | ✅ | lo pide el RF |
| Reportes ([[RF25]]) | ✅ | ✅ | ✅ | lo pide el RF |
| Auditoría ([[RF05]]) | ✅ | ❌ | ✅ | hasta 10 000 filas |
| Diagnóstico de asistencia | ✅ | ❌ | ✅ | hasta 10 000 filas |

**El PDF no se ofrece donde la exportación puede traer miles de filas.** CSV y Excel se escriben en streaming y no cargan el archivo entero en memoria; dompdf arma el documento completo antes de enviarlo. Un acta de cuarenta estudiantes es su caso; diez mil eventos de auditoría, no.

## Consecuencias
- Dos dependencias nuevas: `openspout/openspout` y `barryvdh/laravel-dompdf`. [[Stack y paquetes]] separa ahora **leer** de **escribir**.
- Las cuatro copias del bloque «abrir php://output, BOM, `fputcsv`» desaparecen: quedan en `Exportacion::csv()`.
- Las descargas, incluido el PDF, se devuelven como `StreamedResponse`. Livewire solo reconoce como descarga un `StreamedResponse` o un `BinaryFileResponse`; con la respuesta normal de dompdf intentaba meter el PDF dentro del JSON y fallaba con «Malformed UTF-8 characters».
- **Lo que esta ADR no resuelve:** los «gráficos» que menciona [[RF25]]. Hoy se exporta la tabla, no la gráfica.
- `spatie/laravel-backup` sigue siendo lo último de [[ADR-016 Paquetes]] sin instalar ni superar.
