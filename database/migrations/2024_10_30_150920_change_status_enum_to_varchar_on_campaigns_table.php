<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Traits\SafeMigration;
return new class extends Migration
{
    use SafeMigration;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add new temporary column
        $this->safeAddColumn('campaigns', 'new_status', function (Blueprint $table) {
            $table->string('new_status', 255)->default('draft')->after('status');
        });
        // Step 2: Copy data from `status` to `new_status`
        DB::table('campaigns')->update(['new_status' => DB::raw('status')]);
        // Step 3: Drop the original `status` column
        $this->safeDropColumn('campaigns', 'status');
        // Step 4: Rename `new_status` to `status` with safe raw SQL
        DB::statement("ALTER TABLE campaigns CHANGE `new_status` `status` VARCHAR(255) NOT NULL DEFAULT 'draft'");
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add original ENUM `status` column
        $this->safeAddColumn('campaigns', 'new_status', function (Blueprint $table) {
            $table->enum('new_status', [
                'draft', 'scheduled', 'active', 'paused', 'completed', 'failed', 'cancelled'
            ])->default('draft')->after('time_zone');
        });
        // Step 2: Copy values back
        DB::table('campaigns')->update(['new_status' => DB::raw('status')]);
        // Step 3: Drop `status`
        $this->safeDropColumn('campaigns', 'status');
        // Step 4: Rename `new_status` back to `status`
        DB::statement("ALTER TABLE campaigns CHANGE `new_status` `status` ENUM('draft','scheduled','active','paused','completed','failed','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
