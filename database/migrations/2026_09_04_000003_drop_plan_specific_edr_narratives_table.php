<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::dropIfExists('edr_plan_narratives'); }
    public function down(): void { /* Superseded by the organization-wide EDR plan document. */ }
};
