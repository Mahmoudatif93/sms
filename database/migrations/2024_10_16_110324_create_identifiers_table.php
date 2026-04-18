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
        Schema::create('identifiers', function (Blueprint $table) {
            $table->id();
            $table->uuid('contact_id'); // Reference to the contact
            $table->string('key'); // Identifier type (e.g., emailaddress, phonenumber)
            $table->string('value'); // The actual value (e.g., email, phone number)
            $table->timestamps();

            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('identifiers', function (Blueprint $table) {
            // Drop the foreign key constraint on 'contact_id'
            $table->dropForeign(['contact_id']);
        });
        Schema::dropIfExists('identifiers');
    }
};
