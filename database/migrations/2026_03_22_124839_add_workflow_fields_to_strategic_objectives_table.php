<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            if (!Schema::hasColumn('strategic_objectives', 'status')) {
                $table->string('status')->default('approved');
            }
            if (!Schema::hasColumn('strategic_objectives', 'rejection_comment')) {
                $table->text('rejection_comment')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropColumn(['status', 'rejection_comment']);
        });
    }
};
