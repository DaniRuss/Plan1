<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->enum('document_type', [
                'BL',            // Bill of Lading (CNTR / Machinery)
                'WAYBILL',       // Truck Waybill (FZ)
                'INVOICE',
                'PACKING_LIST',
                'DO',            // Delivery Order (CNTR / Machinery)
                'DECLARATION_4', // Declaration No.4 (FZ)
                'DJ_T1',         // Djibouti Transit Doc
                'ET_T1',         // Ethiopian Transit Doc
                'GET_PASS',      // FZ only
                'CUSTOMS_APPROVAL',
            ]);
            $table->string('reference_number')->nullable(); // D/O #, Declaration #
            $table->string('file_path')->nullable();
            $table->enum('status', ['missing', 'uploaded', 'verified', 'rejected'])->default('missing');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_documents');
    }
};
