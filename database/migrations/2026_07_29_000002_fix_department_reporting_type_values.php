<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE departments MODIFY reporting_type ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly'");
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->string('reporting_type')->default('weekly')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE departments SET reporting_type = 'weekly' WHERE reporting_type = 'daily'");
            DB::statement("ALTER TABLE departments MODIFY reporting_type ENUM('weekly','monthly') NOT NULL DEFAULT 'weekly'");
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->enum('reporting_type', ['weekly', 'monthly'])->default('weekly')->change();
        });
    }
};
