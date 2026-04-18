<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration; // Import the trait

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safely create the table
        $this->safeCreateTable('message_translation_billings', function (Blueprint $table) {
            $table->id();
            $table->string('language'); // Language of translation
            $table->float('cost'); // Translation cost
            $table->boolean('is_billed')->default(false); // If translation is billed
            $table->timestamps();
            $table->softDeletes();
        });

        // Safely add morphs
        $this->safeAddMorphs('message_translation_billings', 'messageable', 'string');

        // Ensure missing columns are added efficiently
        $this->safeAddColumns('message_translation_billings', [
            'language' => fn(Blueprint $table) => $table->string('language'),
            'cost' => fn(Blueprint $table) => $table->float('cost'),
            'is_billed' => fn(Blueprint $table) => $table->boolean('is_billed')->default(false),
            'deleted_at' => fn(Blueprint $table) => $table->softDeletes(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop morphs safely
        $this->safeDropMorphs('message_translation_billings', 'messageable');

        // Drop columns safely
        $this->safeDropColumns('message_translation_billings', ['language', 'cost', 'is_billed', 'deleted_at']);

        // Drop table safely
        $this->safeDropTable('message_translation_billings');
    }
};
