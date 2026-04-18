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
        $this->safeAddColumn('wallet_transactions', 'meta', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        $this->safeDropColumn('wallet_transactions', 'meta');
    }
};
