<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('strategic_objectives', 'owner_id')) {
            Schema::table('strategic_objectives', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('department_id')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('strategic_objectives', 'owner_id')) {
            Schema::table('strategic_objectives', function (Blueprint $table) {
                $table->dropConstrainedForeignId('owner_id');
            });
        }
    }
};
