<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); // Primary Key
            $table->string('name'); // Category Name
            $table->unsignedBigInteger('parent_id')->nullable(); // For Nested Categories
            $table->timestamps(); // Created at & Updated at

            // Foreign Key for Parent Category
            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    public function down() {
        Schema::dropIfExists('categories');
    }
};
