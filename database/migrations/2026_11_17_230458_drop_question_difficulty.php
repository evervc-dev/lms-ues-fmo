<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las preguntas del banco dejan de llevar dificultad (RF16, ADR-071, informe de QA del 17/11).
 *
 * Se van `questions.difficulty` y `quiz_question_rules.difficulty`, y con ellas el filtro por dificultad al sortear.
 * El índice de `questions` pasa a (bank_id, topic), que es como se busca ahora; en SQLite hay que soltarlo antes de
 * borrar la columna, porque no deja quitar una columna indexada.
 *
 * De paso, el CHECK `quantity >= 1` pasa a `>= 0`: la cantidad al azar admite 0 = todas las preguntas del banco con
 * ese tema, y en PostgreSQL la restricción habría rechazado esa regla (en SQLite no existe, ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['bank_id', 'topic', 'difficulty']);
            $table->dropColumn('difficulty');
            $table->index(['bank_id', 'topic']);
        });

        Schema::table('quiz_question_rules', function (Blueprint $table) {
            $table->dropColumn('difficulty');
        });

        if (Adaptive::isPgsql()) {
            DB::statement('ALTER TABLE quiz_question_rules DROP CONSTRAINT IF EXISTS quiz_question_rules_quantity');
        }
        Adaptive::check('quiz_question_rules', 'quiz_question_rules_quantity', 'quantity >= 0');
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement('ALTER TABLE quiz_question_rules DROP CONSTRAINT IF EXISTS quiz_question_rules_quantity');
        }

        Schema::table('quiz_question_rules', function (Blueprint $table) {
            $table->string('difficulty', 10)->nullable();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['bank_id', 'topic']);
            $table->string('difficulty', 10)->nullable();
            $table->index(['bank_id', 'topic', 'difficulty']);
        });

        // Vuelve a exigir al menos una pregunta: falla si ya hay reglas con 0, que hay que corregir antes.
        Adaptive::check('quiz_question_rules', 'quiz_question_rules_quantity', 'quantity >= 1');
    }
};
