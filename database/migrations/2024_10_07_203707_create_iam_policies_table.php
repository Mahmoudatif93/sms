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
        Schema::create('iam_policies', function (Blueprint $table) {
            $table->id();  // ID primary key
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('managed'); // Type of policy (managed, custom, etc.)
            $table->string('scope')->default('organization'); // Policy scope (organization, root, etc.)
            $table->timestamps(); // This will automatically add 'created_at' and 'updated_at'

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iam_policies');
    }
};
