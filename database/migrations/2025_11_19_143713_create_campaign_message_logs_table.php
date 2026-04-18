<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('campaign_message_logs', function (Blueprint $table) {

            $table->id(); // normal primary key

            $table->uuid('campaign_id');
            $table->uuid('contact_id')->nullable();
            $table->string('phone_number')->nullable();

            $table->string('final_status')->default('pending');
            $table->integer('retry_count')->default(0);

            $table->timestamps();
        });

        $this->safeAddForeignKey(
            table: 'campaign_message_logs',
            column: 'campaign_id',
            foreignTable: 'campaigns',
            foreignColumn: 'id',
            constraintName: 'campaign_message_logs_campaign_id_fk',
            onDelete: 'cascade'
        );

        $this->safeAddForeignKey(
            table: 'campaign_message_logs',
            column: 'contact_id',
            foreignTable: 'contacts',
            foreignColumn: 'id',
            constraintName: 'campaign_message_logs_contact_id_fk',
            onDelete: 'set null'
        );
    }

    public function down(): void
    {
        $this->safeDropTable('campaign_message_logs');
    }
};
