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
        $this->safeCreateTable('meta_pages', function (Blueprint $table) {
            $table->string('id')->primary(); // Facebook Page ID
            $table->string('name');
            $table->unsignedBigInteger('business_manager_account_id');

            $table->text('about')->nullable();
            $table->text('bio')->nullable();
            $table->text('description')->nullable();
            $table->string('link')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('website')->nullable();

            $table->timestamps();
        });

        $this->safeAddForeignKey(
            table: 'meta_pages',
            column: 'business_manager_account_id',
            foreignTable: 'business_manager_accounts',
            constraintName: 'meta_pages_business_manager_account_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('meta_pages', 'meta_pages_business_manager_account_id_fk');
        $this->safeDropTable('meta_pages');
    }
};
