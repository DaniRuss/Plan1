<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $latestHistoricalYear = (int) (DB::table('plans')->where('status','approved')->max('year') ?? (now()->year - 1));
        DB::table('settings')->updateOrInsert(['key'=>'manual_period_weight_effective_from_year'], [
            'value'=>(string)($latestHistoricalYear + 1), 'group'=>'workflow', 'type'=>'number',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }
    public function down(): void { DB::table('settings')->where('key','manual_period_weight_effective_from_year')->delete(); }
};
