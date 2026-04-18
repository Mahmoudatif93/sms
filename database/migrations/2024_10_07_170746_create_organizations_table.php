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
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('status', ['active', 'banned'])->default('active');
            $table->string('status_reason')->nullable();
            $table->integer('owner_id'); // Assuming this refers to the user who owns the organization
            $table->timestamps(); // This will automatically add 'created_at' and 'updated_at'

            $table->foreign('owner_id')
                ->references('id')
                ->on('user')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });
        Schema::dropIfExists('organizations');
    }
};
