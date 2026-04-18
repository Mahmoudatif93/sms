<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    public function up(): void {
        $this->safeCreateTable('deal_products', function (Blueprint $table) {
            $table->uuid('deal_id');
            $table->unsignedBigInteger('product_id');
        });

        $this->safeAddForeignKey(
            'deal_products',
            'deal_id',
            'deals',
            onDelete: 'cascade'
        );

        $this->safeAddForeignKey(
            'deal_products',
            'product_id',
            'products',
            onDelete: 'cascade'
        );
    }

    public function down(): void {
        $this->safeDropTable('deal_products');
    }
};
