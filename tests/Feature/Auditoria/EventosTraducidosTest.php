<?php

use App\Modules\Auditoria\Services\AuditoriaService;
use Symfony\Component\Finder\Finder;

/**
 * Los eventos que el código escribe en la auditoría: el segundo argumento de DbContext::run() y
 * DbContext::system() (también en un ternario), y los que se pasan como `evento:` o como valor por
 * defecto de un parámetro `$evento`.
 *
 * @return list<string>
 */
function eventosDeAuditoriaEnElCodigo(): array
{
    $eventos = [];
    $literal = fn (array|string $token): ?string => is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING
        && preg_match('/^[\'"]([a-z_]+(?:\.[a-z_]+)*)[\'"]$/', $token[1], $m) ? $m[1] : null;

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $archivo) {
        $tokens = array_values(array_filter(token_get_all($archivo->getContents()), fn ($t) => ! is_array($t) || ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));

        foreach ($tokens as $i => $token) {
            // DbContext::run( … , <evento> … ) y DbContext::system( … , <evento> )
            if (is_array($token) && $token[1] === 'DbContext' && ($tokens[$i + 1][1] ?? null) === '::'
                && in_array($tokens[$i + 2][1] ?? null, ['run', 'system'], true) && ($tokens[$i + 3] ?? null) === '(') {
                $nivel = 0;
                $argumento = 0;
                for ($j = $i + 3; $j < count($tokens); $j++) {
                    $t = $tokens[$j];
                    if (in_array($t, ['(', '[', '{'], true) || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                        $nivel++;
                    } elseif (in_array($t, [')', ']', '}'], true)) {
                        if (--$nivel === 0) {
                            break;
                        }
                    } elseif ($t === ',' && $nivel === 1) {
                        $argumento++;
                    } elseif ($nivel === 1 && $argumento === 1 && ($evento = $literal($t)) !== null) {
                        $eventos[] = $evento;
                    }
                }
            }

            // corregir(..., evento: 'quiz_attempt.expired') y string $evento = 'quiz_attempt.submitted'
            if (is_array($token) && (($token[1] === 'evento' && ($tokens[$i + 1] ?? null) === ':') || ($token[1] === '$evento' && ($tokens[$i + 1] ?? null) === '='))) {
                if (($evento = $literal($tokens[$i + 2] ?? '')) !== null) {
                    $eventos[] = $evento;
                }
            }
        }
    }

    return array_values(array_unique($eventos));
}

it('traduce todos los eventos que el código escribe en la auditoría', function () {
    $eventos = eventosDeAuditoriaEnElCodigo();
    $sinTraducir = array_values(array_filter($eventos, fn (string $e) => str_starts_with(AuditoriaService::evento($e), __('Otro cambio del sistema (:e)', ['e' => $e]))));

    expect($eventos)->toContain('quiz_attempt.submitted', 'attendance.corrected', 'login')
        ->and($sinTraducir)->toBe([]);
});
