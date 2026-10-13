<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('strategic_objectives', 'target_year')) {
            Schema::table('strategic_objectives', function (Blueprint $table) {
                $table->unsignedSmallInteger('target_year')->nullable()->after('annual_target')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('strategic_objectives', 'target_year')) {
            Schema::table('strategic_objectives', fn (Blueprint $table) => $table->dropColumn('target_year'));
        }
    }
};
