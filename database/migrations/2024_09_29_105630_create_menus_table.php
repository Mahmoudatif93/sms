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
        Schema::create('menus', function (Blueprint $table) { $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('route_name')->nullable(); // To store the route name if needed
            $table->foreignId('parent_id')->nullable()->constrained('menus')->onDelete('cascade'); // Self-referencing parent_id
            $table->foreignId('sub_parent_id')->nullable()->constrained('menus')->onDelete('cascade');
            $table->boolean('operations')->default(0); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
