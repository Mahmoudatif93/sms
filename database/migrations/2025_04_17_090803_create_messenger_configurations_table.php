<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('messenger_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connector_id');
            $table->unsignedBigInteger('business_manager_account_id');
            $table->string('meta_page_id'); // Facebook Page ID
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $this->safeAddForeignKey('messenger_configurations', 'connector_id', 'connectors');
        $this->safeAddForeignKey('messenger_configurations', 'business_manager_account_id', 'business_manager_accounts');
        $this->safeAddForeignKey('messenger_configurations', 'meta_page_id', 'meta_pages', 'id', 'restrict');
    }

    public function down(): void
    {
        $this->safeDropForeignKey('messenger_configurations', 'messenger_configurations_connector_id_fk');
        $this->safeDropForeignKey('messenger_configurations', 'messenger_configurations_business_manager_account_id_fk');
        $this->safeDropForeignKey('messenger_configurations', 'messenger_configurations_meta_page_id_fk');
        $this->safeDropTable('messenger_configurations');
    }
};
