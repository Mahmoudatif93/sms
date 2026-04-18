<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('slug')->nullable()->default(null)->after('id');
            $table->bigInteger('payment_method_id')->unsigned()->nullable()->after('slug');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
            $table->uuid('wallet_id')->collation('utf8mb4_unicode_ci')->nullable()->after('payment_method_id');
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->nullableUuidMorphs('payable');
            $table->enum('transaction_type', [
                'wallet_charge',
                'sms',
                'sms_plan'
            ])->nullable()->after('wallet_id');
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->nullable()->after('transaction_type');
            $table->string('response_message')->nullable()->after('payment_status');
            $table->text('note')->nullable()->after('response_message');
            $table->index(['organization_id', 'status']);
            $table->index(['payment_type', 'payable_type', 'payable_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('slug');
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
            $table->dropForeign(['wallet_id']);
            $table->dropColumn('wallet_id');
            $table->dropMorphs('payable');
            $table->dropColumn('payment_type');
            $table->dropColumn('payment_status');
            $table->dropColumn('response_message');

            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['payment_type', 'payable_type', 'payable_id']);
        });
    }
};
