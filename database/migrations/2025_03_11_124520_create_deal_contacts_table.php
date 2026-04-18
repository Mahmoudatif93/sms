<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    public function up(): void {
        $this->safeCreateTable('deal_contacts', function (Blueprint $table) {
            $table->uuid('deal_id');
            $table->uuid('contact_id');
        });

        $this->safeAddForeignKey(
            'deal_contacts',
            'deal_id',
            'deals',
            onDelete: 'cascade'
        );

        $this->safeAddForeignKey(
            'deal_contacts',
            'contact_id',
            'contacts',
            onDelete: 'cascade'
        );
    }

    public function down(): void {
        $this->safeDropTable('deal_contacts');
    }
};
