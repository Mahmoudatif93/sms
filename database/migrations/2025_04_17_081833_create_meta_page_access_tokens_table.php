<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('meta_page_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('meta_page_id');
            $table->text('access_token');
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            table: 'meta_page_access_tokens',
            column: 'meta_page_id',
            foreignTable: 'meta_pages',
            constraintName: 'meta_page_access_tokens_meta_page_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('meta_page_access_tokens', 'meta_page_access_tokens_meta_page_id_fk');
        $this->safeDropTable('meta_page_access_tokens');
    }
};
