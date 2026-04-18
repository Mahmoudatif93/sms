<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('world_countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('emoji')->nullable();
            $table->string('name_en')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->unique()->nullable();
            $table->string('continent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->safeDropTable('world_countries');
    }
};
