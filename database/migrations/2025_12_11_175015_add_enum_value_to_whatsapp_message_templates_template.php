<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE whatsapp_message_templates MODIFY COLUMN status ENUM('PENDING', 'APPROVED', 'REJECTED', 'PAUSED') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE whatsapp_message_templates MODIFY COLUMN status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL");
    }
};
