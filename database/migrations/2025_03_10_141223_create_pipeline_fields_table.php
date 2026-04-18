<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    public function up()
    {
        $this->safeCreateTable('pipeline_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pipeline_tab_id');
            $table->string('name');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('pipeline_tab_id')->references('id')->on('pipeline_tabs')->onDelete('cascade');
        });

        $this->safeAddForeignKey(
            'pipeline_fields',
            'pipeline_tab_id',
            'pipeline_tabs'
        );
    }

    public function down()
    {
        $this->safeDropTable('pipeline_fields');
    }
};
