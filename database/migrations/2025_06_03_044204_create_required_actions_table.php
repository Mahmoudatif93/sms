<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('required_actions', function (Blueprint $table) {
            $table->id();

            // Type of required action (e.g. 'verify_email', 'submit_documents')
            $table->string('action_type');

            // Polymorphic relation to actionable model (e.g. Organization, User)
            $table->string('actionable_type');
            $table->string('actionable_id'); // Supports UUID or numeric ID

            // Optional metadata for context (e.g. {"reason": "kyc_expired"})
            $table->json('metadata')->nullable();

            // Optional due date, and completion/dismissal tracking
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();

            $table->timestamps();

            // Index for morphs (improves query performance)
            $table->index(['actionable_type', 'actionable_id'], 'required_actions_actionable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop morph fields separately (optional, defensive cleanup)
        $this->safeDropMorphs('required_actions', 'actionable');

        // Drop the entire table
        $this->safeDropTable('required_actions');
    }
};
