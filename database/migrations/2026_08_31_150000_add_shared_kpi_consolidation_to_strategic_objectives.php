<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->foreignId('lead_department_id')->nullable()->after('department_id')
                ->constrained('departments')->nullOnDelete();
            $table->string('company_consolidation_method', 40)->default('lead_department')
                ->after('lead_department_id');
        });

        DB::table('strategic_objectives')->select('id', 'performers')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $performers = json_decode((string) $row->performers, true) ?: [];
                $lead = collect($performers)->map(fn ($id) => (int) $id)->first(fn ($id) => $id > 0);
                if ($lead) {
                    DB::table('strategic_objectives')->where('id', $row->id)->update(['lead_department_id' => $lead]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_department_id');
            $table->dropColumn('company_consolidation_method');
        });
    }
};
