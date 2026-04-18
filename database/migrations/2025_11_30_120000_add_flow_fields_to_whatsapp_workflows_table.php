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
        // Schema::table('whatsapp_workflows', function (Blueprint $table) {
        //     $table->uuid('flow_id')->nullable()->after('id');
        //     $table->string('flow_name')->nullable()->after('flow_id');
        //     $table->text('flow_description')->nullable()->after('flow_name');
            
        //     $table->index('flow_id');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_workflows', function (Blueprint $table) {
            $table->dropIndex(['flow_id']);
            $table->dropColumn(['flow_id', 'flow_name', 'flow_description']);
        });
    }
};

