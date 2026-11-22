<?php

namespace App\Modules\Evaluaciones\Services;

use App\Models\User;
use App\Support\Busqueda;
use App\Support\DbContext;
use App\Support\HojaCalculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Carga de preguntas a un banco desde Excel o CSV (RF16). Una fila por pregunta, con las columnas de COLUMNAS. Primero
 * se analiza el archivo entero y se muestra qué se importará y qué filas tienen errores; la importación es todo o nada,
 * en una transacción, y cada pregunta queda auditada como «question.imported». Es síncrona: un banco tiene a lo sumo
 * cientos de preguntas, no amerita la cola `importaciones`.
 */
class ImportacionPreguntasService
{
    public const COLUMNAS = ['tipo', 'enunciado', 'tema', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'opcion_e', 'opcion_f', 'correcta', 'respuestas_aceptadas', 'justificacion'];

    /** Filas por archivo. */
    public const MAXIMO = 500;

    private const LETRAS = ['a', 'b', 'c', 'd', 'e', 'f'];

    /** Lo que se puede escribir en «tipo», sin tildes, espacios ni signos => tipo de la base. */
    private const TIPOS = [
        'opcionmultiple' => 'multiple_choice', 'multiple' => 'multiple_choice', 'om' => 'multiple_choice',
        'verdaderofalso' => 'true_false', 'vf' => 'true_false',
        'respuestacorta' => 'short_answer', 'corta' => 'short_answer', 'rc' => 'short_answer',
        'ensayo' => 'essay',
    ];

    public function __construct(private CuestionarioService $cuestionarios) {}

    /**
     * Escribe el libro: la hoja «Preguntas» con los encabezados (y las filas que se pasen) y la hoja «Instrucciones»
     * con qué va en cada columna y un ejemplo de cada tipo. Sirve para el formato vacío y para un banco de ejemplo.
     *
     * @param  array<int, array<int, string>>  $filas
     */
    public function escribir(string $destino, array $filas = []): void
    {
        $negrita = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('9F1D2B');
        $ajustado = (new Style)->setShouldWrapText();

        $libro = new Writer;
        $libro->openToFile($destino);
        $hoja = $libro->getCurrentSheet();
        $hoja->setName('Preguntas');
        $hoja->setColumnWidth(18, 1);
        $hoja->setColumnWidth(60, 2);
        $hoja->setColumnWidth(18, 3);
        $hoja->setColumnWidth(24, 4, 5, 6, 7, 8, 9);
        $hoja->setColumnWidth(12, 10);
        $hoja->setColumnWidth(28, 11);
        $hoja->setColumnWidth(40, 12);
        $libro->addRow(Row::fromValues(self::COLUMNAS, $negrita));
        foreach ($filas as $fila) {
            $libro->addRow(Row::fromValues(array_pad(array_values($fila), count(self::COLUMNAS), ''), $ajustado));
        }

        $instrucciones = $libro->addNewSheetAndMakeItCurrent();
        $instrucciones->setName('Instrucciones');
        $instrucciones->setColumnWidth(24, 1);
        $instrucciones->setColumnWidth(90, 2);
        $libro->addRow(Row::fromValues([__('Cómo llenar la hoja «Preguntas»')], (new Style)->setFontBold()));
        $libro->addRow(Row::fromValues([__('Una fila por pregunta. No cambie ni borre la fila de encabezados. Se leen hasta :n filas por archivo.', ['n' => self::MAXIMO])], $ajustado));
        $libro->addRow(Row::fromValues(['']));
        $libro->addRow(Row::fromValues([__('Columna'), __('Qué escribir')], $negrita));
        foreach ([
            'tipo' => __('Obligatorio. «Opción múltiple», «Verdadero / falso», «Respuesta corta» o «Ensayo» (también OM, VF, RC).'),
            'enunciado' => __('Obligatorio. La pregunta, hasta 2000 caracteres.'),
            'tema' => __('Opcional. Sirve para sortear preguntas de un tema en un cuestionario (p. ej., «Normalización»).'),
            'opcion_a … opcion_f' => __('Solo en opción múltiple: de 2 a 6 opciones, en orden. Deje vacías las que no use.'),
            'correcta' => __('Opción múltiple: la letra de la opción correcta (A a F). Verdadero / falso: «Verdadero» o «Falso». En los demás tipos, vacía.'),
            'respuestas_aceptadas' => __('Solo en respuesta corta (obligatoria): las respuestas válidas separadas por punto y coma, p. ej. «SQL; Structured Query Language».'),
            'justificacion' => __('Opcional. Explicación que ve el estudiante al revisar el intento.'),
        ] as $columna => $ayuda) {
            $libro->addRow(Row::fromValues([$columna, $ayuda], $ajustado));
        }
        $libro->addRow(Row::fromValues(['']));
        $libro->addRow(Row::fromValues([__('Ejemplos (cópielos a la hoja «Preguntas» si le sirven)')], (new Style)->setFontBold()));
        $libro->addRow(Row::fromValues(self::COLUMNAS, $negrita));
        foreach (self::ejemplos() as $ejemplo) {
            $libro->addRow(Row::fromValues($ejemplo, $ajustado));
        }
        $libro->close();
    }

    /**
     * Un ejemplo de cada tipo, con las columnas de COLUMNAS.
     *
     * @return array<int, array<int, string>>
     */
    public static function ejemplos(): array
    {
        return [
            ['Opción múltiple', '¿Qué comando de SQL agrega filas a una tabla?', 'SQL', 'SELECT', 'INSERT', 'UPDATE', 'CREATE', '', '', 'B', '', 'INSERT INTO agrega filas; CREATE crea objetos.'],
            ['Verdadero / falso', 'Una clave primaria puede repetirse en la misma tabla.', 'Modelo relacional', '', '', '', '', '', '', 'Falso', '', 'La clave primaria identifica cada fila: no se repite.'],
            ['Respuesta corta', '¿Qué siglas tiene el lenguaje estándar de consultas a bases de datos relacionales?', 'SQL', '', '', '', '', '', '', '', 'SQL; Structured Query Language', ''],
            ['Ensayo', 'Explique con un ejemplo la diferencia entre la primera y la segunda forma normal.', 'Normalización', '', '', '', '', '', '', '', '', ''],
        ];
    }

    /**
     * Lee el archivo y valida cada fila sin escribir nada.
     *
     * @return array{preguntas: list<array{fila: int, tipo: string, enunciado: string, tema: string|null, opciones: list<string>, correcta: int|null, aceptadas: list<string>, justificacion: string|null}>, errores: list<string>}
     *
     * @throws ValidationException si el archivo no se puede leer o no tiene las columnas.
     */
    public function analizar(string $ruta, string $extension): array
    {
        try {
            $filas = HojaCalculo::leer($ruta, $extension);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['archivo' => __('No se pudo leer el archivo. Use el formato .xlsx descargado o un CSV.')]);
        }

        $encabezado = array_map(fn (string $c) => str_replace([' ', '-'], '_', mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', $c)))), array_shift($filas) ?? []);
        $faltan = array_diff(['tipo', 'enunciado'], $encabezado);
        if ($faltan !== []) {
            throw ValidationException::withMessages(['archivo' => __('Al archivo le faltan columnas (:f). Descargue el formato y no cambie la fila de encabezados.', ['f' => implode(', ', $faltan)])]);
        }
        if ($filas === []) {
            throw ValidationException::withMessages(['archivo' => __('El archivo no tiene preguntas: escríbalas debajo de los encabezados, una por fila.')]);
        }
        if (count($filas) > self::MAXIMO) {
            throw ValidationException::withMessages(['archivo' => __('El archivo tiene :n filas; el máximo es :m por archivo. Divídalo en varios.', ['n' => count($filas), 'm' => self::MAXIMO])]);
        }

        $preguntas = [];
        $errores = [];
        foreach ($filas as $i => $linea) {
            $celdas = array_combine($encabezado, array_pad(array_slice($linea, 0, count($encabezado)), count($encabezado), ''));
            $numero = $i + 2;
            $resultado = $this->fila($celdas);
            if (is_string($resultado)) {
                $errores[] = __('Fila :n: :e', ['n' => $numero, 'e' => $resultado]);
            } else {
                $preguntas[] = ['fila' => $numero] + $resultado;
            }
        }

        return ['preguntas' => $preguntas, 'errores' => $errores];
    }

    /**
     * Importa todas las preguntas del archivo al banco, o ninguna si alguna fila tiene errores.
     *
     * @return int Preguntas importadas.
     *
     * @throws ValidationException
     */
    public function importar(User $docente, int $bancoId, string $ruta, string $extension): int
    {
        $this->cuestionarios->banco($docente, $bancoId);
        $analisis = $this->analizar($ruta, $extension);
        if ($analisis['errores'] !== []) {
            throw ValidationException::withMessages(['archivo' => __('Corrija las filas con errores y vuelva a subir el archivo: no se importó ninguna pregunta.')]);
        }

        DbContext::run(function () use ($bancoId, $analisis): void {
            foreach ($analisis['preguntas'] as $p) {
                $id = DB::table('questions')->insertGetId([
                    'bank_id' => $bancoId, 'question_type' => $p['tipo'], 'statement' => $p['enunciado'], 'topic' => $p['tema'],
                    'accepted_answers' => $p['tipo'] === 'short_answer' ? json_encode($p['aceptadas']) : null,
                    'justification' => $p['justificacion'], 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($p['opciones'] as $posicion => $texto) {
                    DB::table('question_options')->insert(['question_id' => $id, 'text' => $texto, 'is_correct' => $posicion === $p['correcta'],
                        'position' => $posicion, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }, 'question.imported');

        return count($analisis['preguntas']);
    }

    /**
     * Una fila ya validada, o el motivo por el que no sirve.
     *
     * @param  array<string, string>  $c
     * @return array{tipo: string, enunciado: string, tema: string|null, opciones: list<string>, correcta: int|null, aceptadas: list<string>, justificacion: string|null}|string
     */
    private function fila(array $c): array|string
    {
        $clave = (string) preg_replace('/[^a-z]/', '', Busqueda::normalizar($c['tipo'] ?? ''));
        $tipo = self::TIPOS[$clave] ?? null;
        $enunciado = trim($c['enunciado'] ?? '');
        $tema = trim($c['tema'] ?? '');
        $justificacion = trim($c['justificacion'] ?? '');
        $correcta = mb_strtolower(trim($c['correcta'] ?? ''));

        if ($tipo === null) {
            return ($c['tipo'] ?? '') === ''
                ? __('falta el tipo.')
                : __('el tipo «:t» no existe; use Opción múltiple, Verdadero / falso, Respuesta corta o Ensayo.', ['t' => $c['tipo']]);
        }
        if ($enunciado === '') {
            return __('falta el enunciado.');
        }
        if (mb_strlen($enunciado) > 2000) {
            return __('el enunciado pasa de 2000 caracteres.');
        }
        if (mb_strlen($tema) > 100) {
            return __('el tema pasa de 100 caracteres.');
        }
        if (mb_strlen($justificacion) > 2000) {
            return __('la justificación pasa de 2000 caracteres.');
        }

        $opciones = [];
        $indice = null;
        $aceptadas = [];
        switch ($tipo) {
            case 'multiple_choice':
                foreach (self::LETRAS as $letra) {
                    $texto = trim($c['opcion_'.$letra] ?? '');
                    if ($texto !== '') {
                        if ($letra === $correcta) {
                            $indice = count($opciones);
                        }
                        $opciones[] = $texto;
                    }
                }
                if (count($opciones) < 2) {
                    return __('una pregunta de opción múltiple necesita al menos dos opciones.');
                }
                if ($indice === null) {
                    return in_array($correcta, self::LETRAS, true)
                        ? __('la opción :l marcada como correcta está vacía.', ['l' => mb_strtoupper($correcta)])
                        : __('en «correcta» escriba la letra de la opción correcta (A a F).');
                }
                break;
            case 'true_false':
                $indice = match ((string) preg_replace('/[^a-z]/', '', Busqueda::normalizar($correcta))) {
                    'verdadero', 'v', 'true', 'si' => 0,
                    'falso', 'f', 'false', 'no' => 1,
                    default => null,
                };
                if ($indice === null) {
                    return __('en «correcta» escriba Verdadero o Falso.');
                }
                $opciones = [__('Verdadero'), __('Falso')];
                break;
            case 'short_answer':
                $aceptadas = array_values(array_filter(array_map('trim', preg_split('/[;|]/', $c['respuestas_aceptadas'] ?? '') ?: []), fn ($r) => $r !== ''));
                if ($aceptadas === []) {
                    return __('una pregunta de respuesta corta necesita al menos una respuesta aceptada.');
                }
                break;
        }

        return [
            'tipo' => $tipo, 'enunciado' => $enunciado, 'tema' => $tema === '' ? null : $tema, 'opciones' => $opciones,
            'correcta' => $indice, 'aceptadas' => $aceptadas, 'justificacion' => $justificacion === '' ? null : $justificacion,
        ];
    }
}
