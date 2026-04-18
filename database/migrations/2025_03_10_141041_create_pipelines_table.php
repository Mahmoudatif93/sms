<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    public function up()
    {
        $this->safeCreateTable('pipelines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->integer('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('user')->onDelete('set null');
            $table->string('color')->nullable();

            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'pipelines',
            'assigned_to',
            'user'
        );
    }

    public function down()
    {
        $this->safeDropTable('pipelines');
    }
};
