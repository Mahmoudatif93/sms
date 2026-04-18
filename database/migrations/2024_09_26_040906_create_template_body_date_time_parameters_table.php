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
        Schema::create('template_body_date_time_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_message_body_component_id');
            $table->string('fallback_value');
            $table->unsignedTinyInteger('day_of_week')->nullable();   // 1 (Monday) to 7 (Sunday)
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();         // 1 to 12
            $table->unsignedTinyInteger('day_of_month')->nullable();  // 1 to 31
            $table->unsignedTinyInteger('hour')->nullable();          // 0 to 23
            $table->unsignedTinyInteger('minute')->nullable();        // 0 to 59
            $table->string('calendar')->default('GREGORIAN'); // Calendar type (default to GREGORIAN)

            $table->timestamps();

            $table->foreign('template_message_body_component_id', 'datetime_param_body_component_fk')
                ->references('id')
                ->on('template_message_body_components')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_body_date_time_parameters', function (Blueprint $table) {
            $table->dropForeign(['template_message_body_component_id']);
        });
        Schema::dropIfExists('template_body_date_time_parameters');
    }
};
