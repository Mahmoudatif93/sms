<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Traits\SafeMigration;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
//        /*
//        |--------------------------------------------------------------------------
//        | notification_logs
//        |--------------------------------------------------------------------------
//        */
//        $this->safeCreateTable('notification_logs', function (Blueprint $table) {
//            $table->uuid('id')->primary();
//            $table->string('notification_id')->index();
//            $table->string('type')->index();
//            $table->string('channel')->index();
//            $table->string('recipient_type')->nullable();
//            $table->unsignedBigInteger('recipient_id')->nullable();
//            $table->string('recipient_identifier')->nullable();
//            $table->string('title')->nullable();
//            $table->text('content');
//            $table->json('data')->nullable();
//
//            $table->enum('status', [
//                'pending', 'queued', 'sending', 'sent',
//                'delivered', 'read', 'failed', 'cancelled', 'scheduled'
//            ])->default('pending')->index();
//
//            $table->string('external_id')->nullable();
//            $table->text('error_message')->nullable();
//            $table->timestamp('sent_at')->nullable();
//            $table->timestamp('delivered_at')->nullable();
//            $table->timestamp('read_at')->nullable();
//            $table->unsignedInteger('retry_count')->default(0);
//            $table->timestamp('next_retry_at')->nullable();
//            $table->unsignedBigInteger('user_id')->nullable();
//
//            // ✅ match workspace/org uuid types
//            $table->uuid('workspace_id')->nullable();
//            $table->uuid('organization_id')->nullable();
//
//            $table->string('template_id')->nullable();
//            $table->json('template_variables')->nullable();
//            $table->enum('priority', ['urgent', 'high', 'normal', 'low'])->default('normal');
//            $table->json('metadata')->nullable();
//            $table->timestamp('scheduled_at')->nullable();
//            $table->timestamps();
//
//            $table->index(['status', 'next_retry_at']);
//            $table->index(['user_id', 'type']);
//            $table->index(['workspace_id', 'type']);
//            $table->index(['organization_id', 'type']);
//            $table->index(['created_at', 'status']);
//        });
//
//        // ✅ Safe FK attachments
//        $this->safeAddForeignKey(
//            table: 'notification_logs',
//            column: 'workspace_id',
//            foreignTable: 'workspaces',
//            foreignColumn: 'id',
//            constraintName: 'notification_logs_workspace_id_fk',
//            onDelete: 'set null'
//        );
//
//        $this->safeAddForeignKey(
//            table: 'notification_logs',
//            column: 'organization_id',
//            foreignTable: 'organizations',
//            foreignColumn: 'id',
//            constraintName: 'notification_logs_organization_id_fk',
//            onDelete: 'set null'
//        );
//
//        /*
//        |--------------------------------------------------------------------------
//        | notification_preferences
//        |--------------------------------------------------------------------------
//        */
//        $this->safeCreateTable('notification_preferences', function (Blueprint $table) {
//            $table->id();
//            $table->enum('entity_type', ['user', 'workspace', 'organization']);
//            $table->unsignedBigInteger('entity_id');
//            $table->string('notification_type');
//            $table->string('channel');
//            $table->boolean('enabled')->default(true);
//            $table->enum('frequency', ['immediate', 'hourly', 'daily', 'weekly', 'never'])->default('immediate');
//            $table->time('quiet_hours_start')->nullable();
//            $table->time('quiet_hours_end')->nullable();
//            $table->json('days_of_week')->nullable();
//            $table->unsignedInteger('max_per_hour')->nullable();
//            $table->unsignedInteger('max_per_day')->nullable();
//            $table->json('settings')->nullable();
//            $table->timestamps();
//
//            $table->unique(
//                ['entity_type', 'entity_id', 'notification_type', 'channel'],
//                'notification_preferences_unique'
//            );
//            $table->index(['entity_type', 'entity_id']);
//            $table->index(['notification_type', 'channel']);
//        });
//
//        /*
//        |--------------------------------------------------------------------------
//        | notification_templates
//        |--------------------------------------------------------------------------
//        */
//        $this->safeCreateTable('notification_templates', function (Blueprint $table) {
//            $table->uuid('id')->primary();
//            $table->string('name');
//            $table->string('type');
//            $table->text('description')->nullable();
//            $table->json('supported_channels');
//            $table->json('supported_locales')->nullable();
//            $table->json('required_variables')->nullable();
//            $table->json('optional_variables')->nullable();
//            $table->json('content');
//            $table->json('metadata')->nullable();
//            $table->boolean('is_active')->default(true);
//            $table->string('created_by')->nullable();
//            $table->timestamps();
//
//            $table->index(['type', 'is_active']);
//            $table->index('name');
//        });
//
//        // ✅ Safe default-locale update
//        if (Schema::hasTable('notification_templates')) {
//            try {
//                DB::statement(
//                    "UPDATE notification_templates
//                     SET supported_locales = '[\"ar\"]'
//                     WHERE supported_locales IS NULL"
//                );
//            } catch (\Throwable $e) {
//                logger()->warning('Skipping supported_locales update: '.$e->getMessage());
//            }
//        }
    }

    public function down(): void
    {
//        $this->safeDropForeignKey('notification_logs', 'notification_logs_workspace_id_fk');
//        $this->safeDropForeignKey('notification_logs', 'notification_logs_organization_id_fk');
//
//        Schema::dropIfExists('notification_templates');
//        Schema::dropIfExists('notification_preferences');
//        Schema::dropIfExists('notification_logs');
    }
};
