<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->boolean('replace_files')
                ->default(false)
                ->after('position'); // أو أي عمود تحبه
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('replace_files');
        });
    }
};
