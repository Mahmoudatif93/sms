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
        Schema::create('conversion_requests', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
            $table->foreignId('wallet_transaction_id')->constrained()->onDelete('cascade'); 
            $table->integer('balance_log_id')->nullable();
            $table->string('conversion_type'); // points_to_currency or currency_to_points
            $table->decimal('amount', 15, 2);
            $table->integer('points');
            $table->enum('status',['pending','approved','rejected']);
            $table->string('handled_by')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion_requests');
    }
};
