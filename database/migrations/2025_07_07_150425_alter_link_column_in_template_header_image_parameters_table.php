<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeChangeOrCreateColumn(
            'template_header_image_parameters',
            'link',
            function (Blueprint $table) {
                $table->text('link')->change();
            },
            function (Blueprint $table) {
                $table->text('link');
            }
        );
    }

    public function down(): void
    {
        $this->safeChangeOrCreateColumn(
            'template_header_image_parameters',
            'link',
            function (Blueprint $table) {
                $table->string('link')->change();
            },
            function (Blueprint $table) {
                $table->string('link');
            }
        );
    }
};
