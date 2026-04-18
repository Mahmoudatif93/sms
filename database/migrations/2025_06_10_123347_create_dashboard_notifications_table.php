<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('dashboard_notifications', function (Blueprint $table) {
            $table->id()->primary();
            $table->string('title');
            $table->text('message')->nullable(); // HTML or markdown-safe content
            $table->string('icon')->nullable();  // e.g., 'alert-circle', 'check-circle'
            $table->string('link')->nullable();  // Where to take the user
            $table->string('category')->nullable(); // e.g., 'system', 'billing', etc.
            // Morph relation to notifiable (e.g. RequiredAction)
            $table->string('notifiable_id')->nullable();
            $table->string('notifiable_type')->nullable();

            $table->index(['notifiable_type', 'notifiable_id']);

            // If the notification is related to workspace or organization
            $table->foreignUuid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('read_at')->nullable(); // When it was read
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safely drop foreign keys before dropping table
        $this->safeDropForeignKey('dashboard_notifications', 'dashboard_notifications_organization_id_foreign');
        $this->safeDropForeignKey('dashboard_notifications', 'dashboard_notifications_workspace_id_foreign');


        $this->safeDropTable('dashboard_notifications');
    }
};
