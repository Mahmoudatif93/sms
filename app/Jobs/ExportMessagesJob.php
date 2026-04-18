<?php
namespace App\Jobs;

use App\Exports\MessagesExport;
use App\Models\Message;
use App\Models\MessageDetails;
use App\Services\FileUploadService;
use App\Services\SendLoginNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ExportMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filters;
    protected $workspaceId;
    protected $messageId;
    protected $userEmail;
    protected $exportType;
    protected $userType;
    protected $messgeType;
    public function __construct($filters, $workspaceId, $messageId, $userEmail, $exportType,$userType=null,$messgeType=null)
    {
        $this->filters     = $filters;
        $this->workspaceId = $workspaceId;
        $this->messageId   = $messageId;
        $this->userEmail   = $userEmail;
        $this->exportType   = $exportType;
        $this->userType   = $userType;
        $this->messgeType   = $messgeType;
    }

    public function handle(FileUploadService $fileUploadService, SendLoginNotificationService $sendEmailNotification)
    {
        // Build the query based on filters
        if ($this->userType === 'admin') {

                $controller = match ($this->messgeType) {
                    'admin'    => 'App\Http\Controllers\Admin\AdminMessageController',
                    'revision' => 'App\Http\Controllers\Admin\RevisionMessageController',
                    default    => 'App\Http\Controllers\Admin\MessageController',
                };


            $query = app()->call("$controller@getExportQuery", [
                'filters' => $this->filters,
                'workspaceId' => $this->workspaceId,
                'messageId' => $this->messageId,
                'exportType' => $this->exportType,
            ]);
        } else {
            $query = app()->call('App\Http\Controllers\SmsUsers\SmsController@getExportQuery', [
                'filters' => $this->filters,
                'workspaceId' => $this->workspaceId,
                'messageId' => $this->messageId,
                'exportType' => $this->exportType,
            ]);
        }


        $fileName = $this->messageId === null
        ? 'messages_export_' . time() . '.xlsx'
        : 'messageDetails_export_' . time() . '.xlsx';

        $filePath = 'exports/' . $fileName;

        // Export the file content
        $excelContent = Excel::raw(new MessagesExport($query, 100000, $this->messageId,$this->exportType), \Maatwebsite\Excel\Excel::XLSX);

        // Upload the file
        $fileUploadService->uploadFromContent($excelContent, 'oss', $filePath);

        // Get the signed URL
        $fileUrl = $fileUploadService->getSignUrl($filePath, 3600);
        $body    = 'Your Export is Ready: ' . $fileUrl;

        // Send email notification
        $sendEmailNotification->sendEmailNotification(
            $this->userEmail,
            'Sms Export',
            'Dreams SMS',
            $body
        );
    }
}
