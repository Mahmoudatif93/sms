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
        $this->safeAddColumns('organizations', [
            'auto_translation_enabled' => fn(Blueprint $table) => $table->boolean('auto_translation_enabled')->default(false)->after('status_reason'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropColumns('organizations', ['auto_translation_enabled']);
    }
};
