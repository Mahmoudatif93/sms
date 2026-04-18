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
        $this->safeAddColumn('wallets', 'pending_amount', function (Blueprint $table) {
            $table->float('pending_amount')->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        $this->safeDropColumn('wallets', 'pending_amount');
    }
};
