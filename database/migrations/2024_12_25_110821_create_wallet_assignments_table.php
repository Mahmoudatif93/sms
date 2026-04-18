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
        Schema::create('wallet_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('wallet_id');
            $table->uuidMorphs('assignable'); // Creates assignable_type and assignable_id
            $table->enum('assignment_type', ['primary', 'secondary'])->default('primary');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('wallet_id')
                ->references('id')
                ->on('wallets')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unique(['wallet_id', 'assignable_type', 'assignable_id', 'assignment_type'], 'unique_wallet_assignment');
       
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_assignments');
    }
};
