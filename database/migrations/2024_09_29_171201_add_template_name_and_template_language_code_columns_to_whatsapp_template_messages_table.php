<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_template_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_template_messages', 'template_name')) {
                $table->string('template_name');
            }
            if (!Schema::hasColumn('whatsapp_template_messages', 'template_language_code')) {
                $table->string('template_language_code');
            }


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_template_messages', function (Blueprint $table) {
            if(Schema::hasColumn('whatsapp_template_messages', 'template_name')) {
                $table->dropColumn('template_name');
            }
            if(Schema::hasColumn('whatsapp_template_messages', 'template_language_code')) {
                $table->dropColumn('template_language_code');
            }


        });
    }
};
