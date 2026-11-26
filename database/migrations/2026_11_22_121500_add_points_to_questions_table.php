<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->decimal('points', 4, 2)->default(1.00);
        });

        Adaptive::check('questions', 'questions_points_check', 'points > 0 AND points <= 10');
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement('ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_points_check');
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
