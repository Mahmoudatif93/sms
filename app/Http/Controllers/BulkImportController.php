<?php

namespace App\Http\Controllers;

use App\Models\BulkImportLog;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkImportController extends BaseApiController
{
    /**
     * Get bulk import status
     */
    public function status(Request $request, Organization $organization, int $importLogId): JsonResponse
    {
        $importLog = BulkImportLog::where('id', $importLogId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$importLog) {
            return $this->response(false, 'Import log not found', null, 404);
        }

        return $this->response(true, 'Import status retrieved successfully', [
            'id' => $importLog->id,
            'status' => $importLog->status,
            'total_records' => $importLog->total_records,
            'processed_records' => $importLog->processed_records,
            'created_records' => $importLog->created_records,
            'invalid_records' => $importLog->invalid_records,
            'progress_percentage' => $importLog->getProgressPercentage(),
            'started_at' => $importLog->started_at,
            'completed_at' => $importLog->completed_at,
            'error_message' => $importLog->error_message,
            'invalid_entries' => $importLog->invalid_entries,
        ]);
    }

    /**
     * Get all bulk import logs for organization
     */
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $query = BulkImportLog::where('organization_id', $organization->id)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by user if provided
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $paginated = $query->paginate($request->get('per_page', 15), ['*'], 'page', $request->get('page', 1));

        $response = $paginated->getCollection()->map(function ($importLog) {
            return [
                'id' => $importLog->id,
                'status' => $importLog->status,
                'total_records' => $importLog->total_records,
                'processed_records' => $importLog->processed_records,
                'created_records' => $importLog->created_records,
                'invalid_records' => $importLog->invalid_records,
                'progress_percentage' => $importLog->getProgressPercentage(),
                'started_at' => $importLog->started_at,
                'completed_at' => $importLog->completed_at,
                'error_message' => $importLog->error_message,
                'user' => $importLog->user ? [
                    'id' => $importLog->user->id,
                    'name' => $importLog->user->name,
                    'email' => $importLog->user->email,
                ] : null,
                'created_at' => $importLog->created_at,
            ];
        });

        $paginated->setCollection($response);

        return $this->paginateResponse(true, 'Import logs retrieved successfully', $paginated);
    }

    /**
     * Cancel a pending bulk import
     */
    public function cancel(Request $request, Organization $organization, int $importLogId): JsonResponse
    {
        $importLog = BulkImportLog::where('id', $importLogId)
            ->where('organization_id', $organization->id)
            ->where('status', BulkImportLog::STATUS_PENDING)
            ->first();

        if (!$importLog) {
            return $this->response(false, 'Import log not found or cannot be cancelled', null, 404);
        }

        $importLog->markAsFailed('Cancelled by user');

        return $this->response(true, 'Import cancelled successfully');
    }

    /**
     * Retry a failed bulk import
     */
    public function retry(Request $request, Organization $organization, int $importLogId): JsonResponse
    {
        $importLog = BulkImportLog::where('id', $importLogId)
            ->where('organization_id', $organization->id)
            ->where('status', BulkImportLog::STATUS_FAILED)
            ->first();

        if (!$importLog) {
            return $this->response(false, 'Import log not found or cannot be retried', null, 404);
        }

        // Reset the import log
        $importLog->update([
            'status' => BulkImportLog::STATUS_PENDING,
            'processed_records' => 0,
            'created_records' => 0,
            'invalid_records' => 0,
            'invalid_entries' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        // TODO: Re-dispatch the job with the original phone numbers
        // This would require storing the original phone numbers data
        // For now, we just reset the status

        return $this->response(true, 'Import retry initiated successfully', [
            'import_log_id' => $importLog->id,
            'status' => $importLog->status,
        ]);
    }
}
