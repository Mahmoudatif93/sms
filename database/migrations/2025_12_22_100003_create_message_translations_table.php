<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('message_translations', function (Blueprint $table) {
            $table->id();
            $table->string('messageable_id');
            $table->string('messageable_type');
            $table->string('source_language', 10)->nullable();
            $table->string('target_language', 10);
            $table->text('translated_text');
            $table->timestamps();

            $table->unique(['messageable_id', 'messageable_type', 'target_language'], 'msg_translation_unique');
            $table->index(['messageable_id', 'messageable_type'], 'msg_translation_messageable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropTable('message_translations');
    }
};
