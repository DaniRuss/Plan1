<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('edr_feedbacks', function(Blueprint $table){
  $table->id(); $table->foreignId('department_id')->constrained()->cascadeOnDelete();
  $table->unsignedInteger('year'); $table->string('reporting_period',30);
  $table->text('strengths')->nullable(); $table->text('weaknesses')->nullable(); $table->text('next_focus_areas')->nullable();
  $table->decimal('plan_quality_score',5,2)->default(0); $table->decimal('report_quality_score',5,2)->default(0); $table->decimal('submission_score',5,2)->default(0);
  $table->decimal('performance_percent',6,2)->nullable(); $table->decimal('performance_score',5,2)->nullable();
  $table->string('status',60)->default('draft'); $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
  $table->timestamps(); $table->unique(['department_id','year','reporting_period']);
 }); }
 public function down(): void { Schema::dropIfExists('edr_feedbacks'); }
};
