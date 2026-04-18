<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeAddColumn('world_countries', 'meta_pricing_market_id', function (Blueprint $table) {
            $table->unsignedBigInteger('meta_pricing_market_id')->nullable()->after('continent');
        });

        $this->safeAddForeignKey(
            table: 'world_countries',
            column: 'meta_pricing_market_id',
            foreignTable: 'meta_pricing_markets',
            onDelete: 'set null'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('world_countries', 'world_countries_meta_pricing_market_id_fk');

        $this->safeDropColumn('world_countries', 'meta_pricing_market_id');
    }
};
