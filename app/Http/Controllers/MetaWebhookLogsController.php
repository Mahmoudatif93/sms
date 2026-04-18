<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\MetaWebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MetaWebhookLogsController extends BaseApiController
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $query = MetaWebhookLog::query()
            ->orderBy('created_at', 'desc');

        // Filter by processed
        if ($request->filled('processed')) {
            $query->where('processed', filter_var($request->get('processed'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by from (start date)
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }

        // Filter by to (end date)
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        $response = $logs->getCollection()->map(function ($log) {
            return new \App\Http\Responses\MetaWebhookLog($log, true); // true = preview mode
        });

        $logs->setCollection($response);

        return $this->paginateResponse(true, 'Webhook logs retrieved successfully', $logs);
    }

    public function show(MetaWebhookLog $metaWebhookLog)
    {
        return $this->response(
            true,
            'Webhook log retrieved successfully',
            new \App\Http\Responses\MetaWebhookLog($metaWebhookLog) // no preview
        );
    }

    public function update(Request $request, MetaWebhookLog $metaWebhookLog)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'processed' => 'required|boolean',
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $data = $validator->validated();

        $metaWebhookLog->update([
            'processed' => $data['processed'],
            'processed_at' => $data['processed'] ? now() : null,
        ]);

        return $this->response(
            true,
            'Webhook log updated successfully',
            new \App\Http\Responses\MetaWebhookLog($metaWebhookLog->fresh())
        );
    }


}
