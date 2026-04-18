<?php
namespace App\Helpers;

use App\Exports\MessagesExport;
use App\Jobs\ExportMessagesJob;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\BaseApiController;

class ExportSmsHelper extends BaseApiController
{

    public static function exportMessages($controllerInstance, $filters, $messageId, $exportType, $userType = null, $workspaceId = null)
    {
        // Get authenticated user email
        $userEmail = Auth::check() ? Auth::user()->email : auth('admin')->user()->email;

        // Prepare file name based on controller
        $filePrefix = match (get_class($controllerInstance)) {
            'App\Http\Controllers\Admin\AdminMessageController' => 'admin',
            'App\Http\Controllers\Admin\RevisionMessageController' => 'revision',
            'App\Http\Controllers\SmsUsers\SmsController' => '',
            default => 'messages'
        };

        $fileName = $messageId === null
            ? "{$filePrefix}_messages_export_" . time() . ".xlsx"
            : "{$filePrefix}_messageDetails_export_" . time() . ".xlsx";

        // Prepare the query
        $query = $controllerInstance->getExportQuery($filters, $workspaceId, $messageId, $exportType);
        $totalRecords = $query->count();

        // Return if no data exists
        if ($totalRecords === 0) {
            return response()->json(['message' => 'No messages found for export.'], 404);

        }

        // Dispatch job if records exceed 100,000
        if ($totalRecords > 100000) {
            ExportMessagesJob::dispatch($filters, $workspaceId, $messageId, $userEmail, $exportType, $userType,$filePrefix )
                ->onQueue('exports');
                $apiController = new BaseApiController();
                return $apiController->response(true, 'Data exceeds 100,000 records. The file will be sent to your email.', 'email');
            }

        // Otherwise, download the Excel file
        return Excel::download(new MessagesExport($query, 100000, $messageId, $exportType), $fileName);
    }
}
