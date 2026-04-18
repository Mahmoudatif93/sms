<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;


    public function up()
    {
        $this->safeCreateTable('pipeline_tabs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pipeline_id');
            $table->string('name');
            $table->integer('position');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('pipeline_id')->references('id')->on('pipelines')->onDelete('cascade');
        });

        $this->safeAddForeignKey(
            'pipeline_tabs',
            'pipeline_id',
            'pipelines'
        );
    }

    public function down()
    {
        $this->safeDropTable('pipeline_tabs');
    }
};
