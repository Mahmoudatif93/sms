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
        $this->safeAddColumns('organization_whatsapp_settings', [
            'who_pays_meta' => function (Blueprint $table) {
                $table->string('who_pays_meta')
                    ->default('client')
                    ->comment('Who pays Meta: client or provider');
            },
            'wallet_charge_mode' => function (Blueprint $table) {
                $table->string('wallet_charge_mode')
                    ->default('none')
                    ->comment('Wallet charge mode: none, markup_only, meta_only, full');
            },
        ]);
    }

    public function down(): void
    {
        $this->safeDropColumns('organization_whatsapp_settings', [
            'who_pays_meta',
            'wallet_charge_mode',
        ]);
    }
};
