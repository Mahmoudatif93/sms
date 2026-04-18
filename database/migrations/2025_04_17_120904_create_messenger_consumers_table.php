<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('messenger_consumers', function (Blueprint $table) {
            $table->id();
            $table->string('meta_page_id'); // Foreign key to meta_pages
            $table->string('psid')->unique(); // Page-scoped user ID
            $table->string('name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            table: 'messenger_consumers',
            column: 'meta_page_id',
            foreignTable: 'meta_pages',
            constraintName: 'messenger_consumers_meta_page_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('messenger_consumers', 'messenger_consumers_meta_page_id_fk');
        $this->safeDropTable('messenger_consumers');
    }
};
