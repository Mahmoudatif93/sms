<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;
use Exception;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    public static function logEventAudit(array $data)
    {
        try {
            $defaultData = [
                'event_type' => $data['event_type'],
                'event_description' =>$data['event_description'],
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'changes' => $data['changes'],
                'created_by_id' => $data['created_by_id'],
                'created_by_type' => $data['created_by_type'],
                'ip_address' => Request::ip(),
                'user_agent' => Request::header('User-Agent')
            ];

            $data = array_merge($defaultData, $data);
            $data['changes'] = json_encode($data['changes']);
            $data['created_at'] = now();

            return AuditLog::create($data);
        } catch (Exception $e) {
            // Log the error
            Log::error('Error while logging audit data: ' . $e->getMessage());
        }

        return false;
    }
}
