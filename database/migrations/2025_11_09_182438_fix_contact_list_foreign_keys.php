<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        // ✅ Ensure correct foreign keys
        // Drop wrong FKs if they exist (using SafeMigration)
        $this->safeDropForeignKey('contact_list', 'contact_list_list_id_fk');
        $this->safeDropForeignKey('contact_list', 'contact_list_contact_id_fk');

        // ✅ Add correct ones
        $this->safeAddForeignKey(
            table: 'contact_list',
            column: 'list_id',
            foreignTable: 'lists',
            foreignColumn: 'id',
            constraintName: 'contact_list_list_id_fk',
            onDelete: 'cascade'
        );

        $this->safeAddForeignKey(
            table: 'contact_list',
            column: 'contact_id',
            foreignTable: 'contacts',
            foreignColumn: 'id',
            constraintName: 'contact_list_contact_id_fk',
            onDelete: 'cascade'
        );

        // ✅ Ensure unique pair to avoid duplicates
        Schema::table('contact_list', function (Blueprint $table) {
            $table->unique(['list_id', 'contact_id'], 'unique_contact_list_pair');
        });
    }

    public function down(): void
    {
        // Safely drop everything on rollback
        $this->safeDropForeignKey('contact_list', 'contact_list_list_id_fk');
        $this->safeDropForeignKey('contact_list', 'contact_list_contact_id_fk');

        Schema::table('contact_list', function (Blueprint $table) {
            if (Schema::hasColumn('contact_list', 'list_id') && Schema::hasColumn('contact_list', 'contact_id')) {
                $table->dropUnique('unique_contact_list_pair');
            }
        });
    }
};
