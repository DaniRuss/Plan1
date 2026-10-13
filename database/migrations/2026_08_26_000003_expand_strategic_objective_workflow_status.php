<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            // Workflow states evolve as review stages are added. A string avoids
            // MySQL ENUM truncation when a new valid application state is stored.
            $table->string('status', 80)->default('approved')->change();
        });
    }

    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved', 'returned'])
                ->default('approved')
                ->change();
        });
    }
};
