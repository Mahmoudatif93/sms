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
        $this->safeAddColumn('messenger_consumers', 'contact_id', function (Blueprint $table) {
            $table->uuid('contact_id')->nullable()->after('id');
        });

        $this->safeAddForeignKey(
            table: 'messenger_consumers',
            column: 'contact_id',
            foreignTable: 'contacts',
            constraintName: 'messenger_consumers_contact_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('messenger_consumers', 'messenger_consumers_contact_id_fk');

        $this->safeDropColumn('messenger_consumers', 'contact_id');
    }
};
