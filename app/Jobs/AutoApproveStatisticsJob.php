<?php

namespace App\Jobs;

use App\Models\StatisticsProcessing;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class AutoApproveStatisticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $statisticsProcessing;
    protected $user;
    protected $workspace;
    public $timeout = 300; // 5 minutes timeout
    public $tries = 1; // Only try once

    /**
     * Create a new job instance.
     */
    public function __construct(StatisticsProcessing $statisticsProcessing, User $user, Workspace $workspace = null)
    {
        $this->statisticsProcessing = $statisticsProcessing;
        $this->user = $user;
        $this->workspace = $workspace;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Auto-approval job started for processing ID: {$this->statisticsProcessing->processing_id}");

            // Refresh the model to get the latest status
            $this->statisticsProcessing->refresh();

            // Check if the statistics is still in COMPLETED status (not manually approved/rejected)
            if ($this->statisticsProcessing->status !== StatisticsProcessing::STATUS_COMPLETED) {
                Log::info("Statistics processing already handled manually", [
                    'processing_id' => $this->statisticsProcessing->processing_id,
                    'current_status' => $this->statisticsProcessing->status
                ]);
                return;
            }

            // Auto-approve the statistics
            $this->statisticsProcessing->approve(0); // 0 indicates system auto-approval

            Log::info("Statistics auto-approved successfully", [
                'processing_id' => $this->statisticsProcessing->processing_id,
                'user_id' => $this->user->id
            ]);

            // Dispatch the sending job
            ProcessApprovedSendingJob::dispatch(
                $this->statisticsProcessing,
                $this->user,
                $this->workspace
            )->onQueue('sms-normal');

            Log::info("Auto-approved sending job dispatched", [
                'processing_id' => $this->statisticsProcessing->processing_id
            ]);

        } catch (Exception $e) {
            Log::error("Auto-approval job failed", [
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't throw the exception to avoid retries
            // Auto-approval failure should not crash the system
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error("AutoApproveStatisticsJob failed permanently", [
            'processing_id' => $this->statisticsProcessing->processing_id,
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Get the tags for the job (for monitoring)
     */
    public function tags(): array
    {
        return [
            'auto-approve',
            'processing_id:' . $this->statisticsProcessing->processing_id,
            'user_id:' . $this->user->id
        ];
    }
}
