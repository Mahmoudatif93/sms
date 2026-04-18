<?php

namespace App\Http\Controllers\SmsUsers;

use App\Http\Controllers\SmsApiController;
use App\Http\Requests\SmsValidationRequest;
use App\Actions\Sms\ProcessSmsStatisticsAction;
use App\Actions\Sms\SendSmsAction;
use App\Models\Channel;
use App\Models\Workspace;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletNotFoundException;
use App\Exceptions\UserInactiveException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Http\Responses\ValidatorErrorResponse;

class RefactoredSmsController extends SmsApiController implements HasMiddleware
{
    public function __construct(
        private ProcessSmsStatisticsAction $statisticsAction,
        private SendSmsAction $sendAction
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware(function ($request, $next) {
                $user = auth()->user();
                if (!$user || !$user->active || $user->active != 1) {
                    return response()->json([
                        'success' => false,
                        'message' => trans('message.msg_error_user_inactive'),
                        'error_code' => 'USER_INACTIVE'
                    ], 403);
                }
                return $next($request);
            })
        ];
    }

    /**
     * Generate SMS statistics
     */
    public function statistics(SmsValidationRequest $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
           
            $data = $request->getProcessedData();

            $result = $this->statisticsAction->execute($data, $user);
            $success = isset($result['cost']) ? $result['cost'] > 0 : true;
            $message = $success ? trans('message.ok') : 'error';

            return $this->response($success, $message, $result, 200);

        } catch (ValidationException $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), [], 500);
        }
    }

    /**
     * Send SMS directly
     */
    public function send(SmsValidationRequest $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $data = $request->getProcessedData();
            $message = $this->sendAction->execute($data, $user);
            return $this->response(
                true,
                trans('message.msg_send_successfully'),
                ['message_id' => $message->id],
                200
            );

        } catch (UserInactiveException $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        } catch (InsufficientBalanceException $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        } catch (WalletNotFoundException $e) {
            return $this->response(false, $e->getMessage(), [], 404);
        } catch (ValidationException $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), [], 500);
        }
    }


    /**
     * Approve the statistics processing result
     */
    public function approveStatistics(Workspace $workspace,Channel $channel, string $processingId): JsonResponse
    {
        $user = auth('api')->user();

        $statisticsProcessing = \App\Models\StatisticsProcessing::where('processing_id', $processingId)
            ->where('user_id', $user->id)
            ->where('status', \App\Models\StatisticsProcessing::STATUS_COMPLETED)
            ->first();

        if (!$statisticsProcessing) {
            return $this->response(false, 'Processing record not found or not completed', [], 404);
        }

        try {
            // Manual approval - this will prevent auto-approval job from running
            $statisticsProcessing->approve($user->id);

            // Process sending in background for large campaigns
            \App\Jobs\ProcessApprovedSendingJob::dispatch(
                $statisticsProcessing,
                $user,
                $workspace
            )->onQueue('sms-normal');

            return $this->response(
                true,
                'Statistics approved successfully. Sending will be processed in background.',
                [
                    'processing_id' => $processingId,
                    'entries' => $statisticsProcessing->entries_json,
                    'count' => $statisticsProcessing->processed_numbers,
                    'cost' => $statisticsProcessing->total_cost,
                    'sending_type' => 'background',
                    'status' => 'approved'
                ],
                202 // Accepted
            );
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), [], 500);
        }
    }

    public function rejectStatistics(Workspace $workspace,Channel $channel, string $processingId): JsonResponse
    {
        $user = auth('api')->user();

        $statisticsProcessing = \App\Models\StatisticsProcessing::where('processing_id', $processingId)
            ->where('user_id', $user->id)
            ->where('status', \App\Models\StatisticsProcessing::STATUS_COMPLETED)
            ->first();

        if (!$statisticsProcessing) {
            return $this->response(false, 'Processing record not found or not completed', [], 404);
        }

        try {
            // Manual rejection - this will prevent auto-approval job from running
            $statisticsProcessing->reject($user->id);

            return $this->response(
                true,
                'Statistics rejected successfully',
                ['processing_id' => $processingId, 'status' => 'rejected'],
                200
            );
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), [], 500);
        }
    }

    public function reviewStatistics(Workspace $workspace,Channel $channel, $processingId): JsonResponse
    {
        $user = auth('api')->user();

        $statisticsProcessing = \App\Models\StatisticsProcessing::where('processing_id', $processingId)
            ->where('user_id', $user->id)
            ->first();

        if (!$statisticsProcessing) {
            return $this->response(false, 'Processing record not found', [], 404);
        }

        // Prepare response data based on status
        $responseData = [
            'processing_id' => $statisticsProcessing->processing_id,
            'status' => $statisticsProcessing->status,
            'status_text' => __('message.' . strtolower($statisticsProcessing->status)),
            'message' => $statisticsProcessing->message,
            'sender_name' => $statisticsProcessing->sender_name,
            'sms_type' => $statisticsProcessing->sms_type,
            'send_time_method' => $statisticsProcessing->send_time_method,
            'send_time' => $statisticsProcessing->send_time,
            'repeation_times' => $statisticsProcessing->repeation_times,
            'message_length' => $statisticsProcessing->message_length,
            'total_numbers' => $statisticsProcessing->total_numbers,
            'processed_numbers' => $statisticsProcessing->processed_numbers,
            'total_cost' => $statisticsProcessing->total_cost,
            'started_at' => $statisticsProcessing->started_at,
            'completed_at' => $statisticsProcessing->completed_at,
            'error_message' => $statisticsProcessing->error_message,
        ];

        // Add detailed results if processing is completed
        if ($statisticsProcessing->isCompleted()) {
            $responseData['entries'] = $statisticsProcessing->entries_json;
            $responseData['can_approve'] = true;
            $responseData['can_reject'] = true;
        } elseif ($statisticsProcessing->isFailed()) {
            $responseData['can_retry'] = true;
        } elseif ($statisticsProcessing->isProcessing()) {
            $responseData['progress_percentage'] = $statisticsProcessing->getProgressPercentage();
        }

        // Add approval/rejection info if applicable
        if ($statisticsProcessing->isApproved() || $statisticsProcessing->isRejected()) {
            $responseData['approved_at'] = $statisticsProcessing->approved_at;
            $responseData['approved_by'] = $statisticsProcessing->approved_by;
        }

        return $this->response(
            true,
            'Statistics processing details retrieved successfully',
            $responseData,
            200
        );
    }

}
