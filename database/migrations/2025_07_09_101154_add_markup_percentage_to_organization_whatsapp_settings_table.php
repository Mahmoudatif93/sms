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
        $this->safeAddColumn('organization_whatsapp_settings', 'markup_percentage', function (Blueprint $table) {
            $table->float('markup_percentage')->nullable()->after('wallet_charge_mode');
        });
    }

    public function down(): void
    {
        $this->safeDropColumn('organization_whatsapp_settings', 'markup_percentage');
    }
};
