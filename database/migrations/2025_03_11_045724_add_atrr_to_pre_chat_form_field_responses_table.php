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
        Schema::table('pre_chat_form_field_responses', function (Blueprint $table) {
            $table->uuid('contact_id')->nullable()->after('visitor_id');
            $table->foreign('contact_id')->references('id')->on('contacts');
            $table->uuid('visitor_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pre_chat_form_field_responses', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn(['contact_id']);
            $table->uuid('visitor_id')->nullable(false)->change();
        });
    }
};
