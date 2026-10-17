<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('reporting_submissions',function(Blueprint $table){$table->json('department_snapshot')->nullable(); $table->timestamp('department_approved_at')->nullable();}); }
 public function down(): void { Schema::table('reporting_submissions',fn(Blueprint $table)=>$table->dropColumn(['department_snapshot','department_approved_at'])); }
};
