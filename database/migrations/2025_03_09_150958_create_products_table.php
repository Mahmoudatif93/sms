<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // Primary Key
            $table->string('name'); // Product Name
            $table->text('description')->nullable(); // Product Description
            $table->decimal('price', 10, 2); // Product Price
            $table->integer('stock'); // Stock Quantity
            $table->string('sku')->unique(); // Unique SKU for the Product
            $table->unsignedBigInteger('category_id'); // Foreign Key to Categories
            $table->timestamps(); // Created at & Updated at

            // Foreign Key Constraint
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    public function down() {
        Schema::dropIfExists('products');
    }
};
