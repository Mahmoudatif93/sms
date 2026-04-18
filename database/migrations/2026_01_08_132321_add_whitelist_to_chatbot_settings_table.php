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
        Schema::table('chatbot_settings', function (Blueprint $table) {
            // تفعيل الـ whitelist (إذا true = يرد فقط على الأرقام المحددة)
            $table->boolean('whitelist_enabled')->default(false)->after('use_knowledge_search');
            // الأرقام المسموحة مفصولة بفاصلة: "966501234567,966507654321"
            $table->text('whitelist_contacts')->nullable()->after('whitelist_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_settings', function (Blueprint $table) {
            $table->dropColumn(['whitelist_enabled', 'whitelist_contacts']);
        });
    }
};
