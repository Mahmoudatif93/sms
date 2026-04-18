<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /* 'points_cnt',
        'price',
        'currency',
        'is_custom',
        'is_active',
        */
        Schema::create('organization_plan', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->integer('plan_id');
            $table->integer('points_cnt');
            $table->string('currency',5);
            $table->double('price');
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
            $table->unique(['organization_id', 'plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_plan');
    }
};
