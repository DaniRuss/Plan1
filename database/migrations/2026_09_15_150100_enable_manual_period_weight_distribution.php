<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(['key'=>'period_weight_distribution_mode'], ['value'=>'manual','group'=>'workflow','type'=>'select','created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void
    {
        DB::table('settings')->where('key','period_weight_distribution_mode')->delete();
    }
};
