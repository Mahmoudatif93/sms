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
        $this->safeAddColumns('conversations', [
            'detected_language' => fn(Blueprint $table) => $table->string('detected_language', 10)->nullable()->after('status'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropColumns('conversations', ['detected_language']);
    }
};
