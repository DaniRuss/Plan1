<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->string('target_distribution')->default('checkpoint')->after('annual_target');

            foreach (['july', 'august', 'september', 'october', 'november', 'december',
                'january', 'february', 'march', 'april', 'may', 'june'] as $month) {
                $table->decimal($month . '_target', 16, 4)->nullable();
            }

            foreach (['q1', 'q2', 'q3', 'q4'] as $quarter) {
                $table->decimal($quarter . '_target', 16, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $columns = ['target_distribution'];
            foreach (['july', 'august', 'september', 'october', 'november', 'december',
                'january', 'february', 'march', 'april', 'may', 'june'] as $month) {
                $columns[] = $month . '_target';
            }
            foreach (['q1', 'q2', 'q3', 'q4'] as $quarter) {
                $columns[] = $quarter . '_target';
            }
            $table->dropColumn($columns);
        });
    }
};
