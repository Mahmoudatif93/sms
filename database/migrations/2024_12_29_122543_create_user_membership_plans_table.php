<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_membership_plans', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');// Match users.id type (unsigned big integer)
            $table->unsignedBigInteger('service_id'); // Match services.id type (unsigned big integer)
            $table->decimal('price', 10, 2);
            $table->string('frequency');
            $table->string('status');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();


            // Foreign key constraints
            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_membership_plans', function (Blueprint $table) {
            // Drop foreign keys explicitly using their names
            $table->dropForeign('user_id');
            $table->dropForeign('service_id');
        });
        Schema::dropIfExists('user_membership_plans');
    }
};
