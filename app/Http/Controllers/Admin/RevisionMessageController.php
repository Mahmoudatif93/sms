<?php
namespace App\Http\Controllers\Admin;

use App\Class\SmsProcessorFactory;
use App\Helpers\ExportSmsHelper;
use App\Helpers\Sms\MessageHelper;
use App\Http\Controllers\SmsApiController;
use App\Models\Message;
use App\Models\MessageDetails;
use App\Models\User;
use App\Models\Outbox;
use App\Models\Workspace;
use App\Services\FileUploadService;
use App\Services\SendLoginNotificationService;
use App\Traits\WalletManager;
use App\Enums\Service as EnumService;
use App\Models\Service as MService;
use App\Contracts\NotificationManagerInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Exports\MessagesExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\ExportMessagesJob;
use App\Services\Sms as SMS;

class RevisionMessageController extends SmsApiController implements HasMiddleware
{
    use WalletManager;

    public static function middleware(): array
    {
        return [
            new Middleware('check.admin'),
        ];
    }
    protected $SendEmailNotification;
    protected $fileUploadService;
    protected $sms;

    public function __construct(
        FileUploadService $fileUploadService,
        SendLoginNotificationService $SendEmailNotification,
        SMS $sms
    ) {
        $this->SendEmailNotification = $SendEmailNotification;
        $this->fileUploadService = $fileUploadService;
        $this->sms = $sms;
    }

    public function index(Request $request)
    {
        $search = $request->search ?? null;
        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });
        $items = Message::revision( $perPage, $search);
        return response()->json([
            'data' => $items->items(),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);

    }

    public function reject($id)
    {
        $message = Message::find($id);
        if (!empty($message)) {
            // Refund the wallet balance before deleting the message
            $this->refundMessageCost($message);

            Message::where('id', $id)->delete();
            MessageDetails::where('message_id', $id)->delete();
            Outbox::where('message_id', $id)->delete();

            return $this->response(true, __('message.msg_message_deleted'));
        }
        return $this->response(false, 'errors', __('message.msg_message_not_available'), 404);
    }

    public function accept($id)
    {
        $message = Message::find($id);
        if (!$message) {
            return $this->response(false, 'errors', __('message.msg_message_not_available'), 404);
        }

        // Check if message is in review status (advertising = 1)
        if ($message->advertising != 1) {
            return $this->response(false, 'errors', 'Message is not in review status', 400);
        }

        try {
            // Update message status to approved (advertising = 2)
            $message->update(['advertising' => 2]);

            // Get admin user info for notification
            $admin = auth()->user();
            $adminName = $admin ? $admin->username : 'Admin';

            // Send Telegram notification about approval
            $this->sendApprovalNotification($message, $adminName);

            // Send the approved message
            $this->sendApprovedMessage($message);

            return $this->response(true, __('message.msg_message_approved_successfully'));

        } catch (\Exception $e) {
            \Log::error('Failed to approve message', [
                'message_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response(false, 'errors', 'Failed to approve message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Refund the cost of a rejected message back to the user's wallet
     */
    private function refundMessageCost(Message $message): bool
    {
        try {
            // Get the user and workspace
            $user = User::find($message->user_id);
            $workspace = Workspace::find($message->workspace_id);

            if (!$user || !$workspace || $message->cost <= 0) {
                return false;
            }

            // Get the SMS service ID
            $serviceId = MService::where('name', EnumService::SMS)->value('id');

            // Get the wallet that was charged for this message
            $wallet = $this->getObjectWallet($workspace, $serviceId, $user->id);

            if (!$wallet) {
                \Log::warning('Wallet not found for refund', [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'workspace_id' => $workspace->id
                ]);
                return false;
            }

            // Refund the cost back to the wallet
            $refundSuccess = $this->changeBalance(
                $wallet,
                $message->cost,
                EnumService::SMS,
                "Refund for rejected message ID: {$message->id}"
            );

            if ($refundSuccess) {
                \Log::info('Message cost refunded successfully', [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'refund_amount' => $message->cost,
                    'wallet_id' => $wallet->id
                ]);
            } else {
                \Log::error('Failed to refund message cost', [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'refund_amount' => $message->cost,
                    'wallet_id' => $wallet->id
                ]);
            }

            return $refundSuccess;

        } catch (\Exception $e) {
            \Log::error('Exception during message cost refund', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function exportMessages(Request $request, $messageId = null)
    {
        $filters = $request->only(['from_date', 'till_date', 'sender_name', 'search', 'number']);
        return ExportSmsHelper::exportMessages($this, $filters, $messageId, $request->exportType, 'admin', null);
    }

    public function getExportQuery($filters, $workspaceId = null, $messageId, $exportType)
    {
        $search = $filters['search'] ?? null;
        if ($messageId === null) {
            return ($exportType === 'details')
                ? MessageDetails::filter($filters, null, 0)
                : Message::where('advertising', 1)
                    ->where('deleted_by_user', 0)
                    ->where('status', 0)
                    ->with(['workspace.organization'])
                    ->when(!empty($search), function ($query) use ($search) {
                        $query->where(function ($subQuery) use ($search) {
                            $subQuery->where('sender_name', 'like', '%' . $search . '%')
                                ->orWhere('count', 'like', '%' . $search . '%')
                                ->orWhere('text', 'like', '%' . $search . '%')
                                ->orWhere('status', 'like', '%' . $search . '%')
                                ->orWhere('channel', 'like', '%' . $search . '%')
                                ->orWhere('cost', 'like', '%' . $search . '%');
                        })
                            ->orWhereHas('workspace', function ($workspaceQuery) use ($search) {
                                $workspaceQuery->where('name', 'like', '%' . $search . '%')
                                    ->orWhereHas('organization', function ($organizationQuery) use ($search) {
                                        $organizationQuery->where('name', 'like', '%' . $search . '%');
                                    });
                            });
                    });
        } else {
            return MessageDetails::where('message_id', $messageId)->filter($filters, null, 0);
        }
    }

    /**
     * Send Telegram notification about message approval using unified notification system
     */
    private function sendApprovalNotification(Message $message, string $adminName): void
    {
        try {
            // Get user information
            $user = User::find($message->user_id);
            $userName = $user ? $user->name : 'Unknown User';

            // Get the centralized notification manager
            $notificationManager = app(NotificationManagerInterface::class);

            // Prepare template variables
            $templateVariables = [
                'user_name' => htmlspecialchars($userName),
                'admin_name' => htmlspecialchars($adminName),
                'message_content' => htmlspecialchars($message->text),
                'sender_name' => htmlspecialchars($message->sender_name),
                'message_count' => number_format($message->count),
                'message_cost' => number_format($message->cost, 2),
                'message_id' => $message->id,
                'approval_time' => date('Y-m-d H:i:s')
            ];

            // Get admin channel settings from config
            $adminChannelId = config('notifications.available_channels.telegram.channels.review', '@dreams_admin');

            // Define recipients for admin channel
            $recipients = [
                ['type' => 'telegram', 'identifier' => $adminChannelId]
            ];

            // Send via centralized notification system
            $result = $notificationManager->sendFromTemplate(
                'message_approval',
                $recipients,
                $templateVariables,
                ['telegram'],
                [
                    'sender_type' => 'admin',
                    'priority' => 'high'
                ]
            );

            if ($result['success']) {
                \Log::info('Message approval notification sent via centralized notification system', [
                    'message_id' => $message->id,
                    'admin_name' => $adminName,
                    'notification_log_id' => $result['notification_log_id'] ?? null,
                    'template_used' => 'message_approval'
                ]);
            } else {
                \Log::error('Failed to send message approval notification via centralized system', [
                    'message_id' => $message->id,
                    'admin_name' => $adminName,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Exception in sendApprovalNotification', [
                'message_id' => $message->id,
                'admin_name' => $adminName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send the approved message using the same processor logic as the rest of the system
     */
    private function sendApprovedMessage(Message $message): void
    {
        try {
            // Get the outbox record for this message
            $outbox = Outbox::get_message_by_message_id($message->id);

            if (!$outbox) {
                // If no outbox found, use direct SMS sending
                $this->sms::sendCampaign($message->id, 0, 0);
                \Log::info('Approved message sent directly (no outbox found)', [
                    'message_id' => $message->id,
                    'user_id' => $message->user_id,
                    'count' => $message->count
                ]);
                return;
            }

            // Use the same processor factory logic as SmsProcessingService
            $processor = SmsProcessorFactory::createProcessor($message->count);

            // Process the outbox if it hasn't been processed yet
            if (!$processor->process($outbox)) {
                throw new \Exception("Message processing failed");
            }

            // Send the message if it's not scheduled and not a review message anymore
            if ($message->sending_datetime == null && $message->advertising == 2) {
                $processor->sendMessage($message->id);
            }

            \Log::info('Approved message sent successfully', [
                'message_id' => $message->id,
                'user_id' => $message->user_id,
                'count' => $message->count,
                'processor_type' => get_class($processor)
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send approved message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Store a newly created revision message in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // This method is required by the resource route but not implemented
        // as revision messages are typically created through other processes
        // and then reviewed by admins
        return $this->response(false, 'Direct creation of revision messages is not supported', null, 405);
    }

}
