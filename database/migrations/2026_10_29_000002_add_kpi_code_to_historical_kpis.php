<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('historical_kpis', function (Blueprint $table) {
            $table->string('kpi_code', 20)->nullable()->after('code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('historical_kpis', fn (Blueprint $table) => $table->dropColumn('kpi_code'));
    }
};
