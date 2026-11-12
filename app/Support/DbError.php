<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Traduce los errores de la base de datos a mensajes en español, de usted (ADR-027).
 */
final class DbError
{
    private const MESSAGES = [
        'AC900' => 'La operación no tiene un usuario responsable. Intente de nuevo desde la aplicación.',
        'AC901' => 'Este registro es histórico y no se puede modificar.',
        'AC902' => 'Este registro no se puede eliminar; puede desactivarlo o retirarlo.',
        'AC203' => 'El período académico está cerrado.',
        '23P01' => 'El aula ya está ocupada en ese horario.',
        '23505' => 'Ya existe un registro con esos datos.',
        '23503' => 'El registro está relacionado con otros datos y no se puede completar la operación.',
        '23514' => 'Los datos no cumplen las reglas del sistema.',
    ];

    public static function toDomain(QueryException $exception): RuntimeException
    {
        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return new RuntimeException(self::MESSAGES[$state] ?? 'No se pudo completar la operación.', 0, $exception);
    }
}
