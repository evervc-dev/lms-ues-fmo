{{--
    Hoja de los PDF que genera la plataforma (ADR-049). dompdf no corre Tailwind ni JavaScript, así que el
    estilo va aquí, en CSS sencillo, y cada exportación solo aporta su tabla.

    <x-exportacion.hoja :titulo="…" :subtitulo="…"> <table>…</table> </x-exportacion.hoja>
--}}
@props(['titulo', 'subtitulo' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 2.2cm 1.6cm 2cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1a171b; }
        header { border-bottom: 2px solid #ae152d; padding-bottom: 8px; margin-bottom: 14px; }
        header .marca { color: #ae152d; font-size: 8pt; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; }
        header h1 { font-size: 14pt; margin: 4px 0 0; }
        header p { font-size: 9pt; color: #696969; margin: 2px 0 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #dcdcde; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f1f1f2; font-size: 8pt; text-transform: uppercase; letter-spacing: .04em; }
        td.num, th.num { text-align: right; }
        tr.total td { background: #f1f1f2; font-weight: bold; }
        .vacio { color: #696969; font-style: italic; padding: 12px 0; }
        .nota { background: #fff7e6; border: 1px solid #f0c36d; color: #6b4a00; padding: 6px 8px; margin: 0 0 8px; }
        footer { position: fixed; bottom: -1.2cm; left: 0; right: 0; font-size: 7.5pt; color: #696969; }
    </style>
</head>
<body>
    <header>
        <p class="marca">{{ __('Universidad de El Salvador · Facultad Multidisciplinaria Oriental') }}</p>
        <h1>{{ $titulo }}</h1>
        @if ($subtitulo)
            <p>{{ $subtitulo }}</p>
        @endif
    </header>

    {{ $slot }}

    <footer>
        {{ __('Generado el :f por :u', ['f' => now()->timezone('America/El_Salvador')->format('d/m/Y H:i'), 'u' => auth()->user()?->fullName() ?? '—']) }}
    </footer>
</body>
</html>
