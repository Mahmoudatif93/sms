<?php

namespace App\Http\Controllers\Api\V1\Messenger;

use App\Domain\Messenger\Actions\CreateMessengerTemplateAction;
use App\Domain\Messenger\Actions\DeleteMessengerTemplateAction;
use App\Domain\Messenger\Actions\DuplicateTemplateAction;
use App\Domain\Messenger\Actions\ToggleTemplateActiveAction;
use App\Domain\Messenger\Actions\UpdateMessengerTemplateAction;
use App\Domain\Messenger\DTOs\CreateMessengerTemplateDTO;
use App\Domain\Messenger\DTOs\MessengerTemplateResultDTO;
use App\Domain\Messenger\DTOs\UpdateMessengerTemplateDTO;
use App\Domain\Messenger\Services\MessengerTemplateService;
use App\Http\Controllers\BaseApiController;
use App\Http\Requests\Messenger\StoreMessengerTemplateRequest;
use App\Http\Requests\Messenger\UpdateMessengerTemplateRequest;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessengerTemplateController extends BaseApiController
{
    public function __construct(
        private MessengerTemplateService $templateService,
        private CreateMessengerTemplateAction $createAction,
        private UpdateMessengerTemplateAction $updateAction,
        private DeleteMessengerTemplateAction $deleteAction,
        private ToggleTemplateActiveAction $toggleActiveAction,
        private DuplicateTemplateAction $duplicateAction,
    ) {}

    private function getMetaPageId(Channel $channel): ?string
    {
        return $channel->connector?->messengerConfiguration?->meta_page_id;
    }

    private function errorResponse(string $message, int $statusCode = 400): JsonResponse
    {
        return $this->response(success: false, message: $message, data: null, statusCode: $statusCode);
    }

    public function index(Request $request, Channel $channel): JsonResponse
    {
        $metaPageId = $this->getMetaPageId($channel);
        if (!$metaPageId) {
            return $this->errorResponse('Messenger configuration not found for this channel');
        }

        $filters = array_filter([
            'type' => $request->input('type'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
        ], fn($v) => $v !== null);

        $templates = $this->templateService->list($metaPageId, $filters, $request->input('per_page', 15));

        return $this->paginateResponse(success: true, message: 'Templates retrieved successfully', items: $templates);
    }

    public function store(StoreMessengerTemplateRequest $request, Channel $channel): JsonResponse
    {
        $metaPageId = $this->getMetaPageId($channel);
        if (!$metaPageId) {
            return $this->errorResponse('Messenger configuration not found for this channel');
        }

        $dto = CreateMessengerTemplateDTO::fromRequest(
            $metaPageId,
            $request->validated(),
            $request->file('images'),
            $request->file('media_file'),
            $request->file('coupon_image')
        );

        $result = $this->createAction->execute($dto);

        return $this->response(success: true, message: 'Template created successfully', data: $result->toArray(), statusCode: 201);
    }

    public function show(Channel $channel, string $templateId): JsonResponse
    {
        $metaPageId = $this->getMetaPageId($channel);
        if (!$metaPageId) {
            return $this->errorResponse('Messenger configuration not found for this channel');
        }

        $template = $this->templateService->find($metaPageId, $templateId);
        if (!$template) {
            return $this->errorResponse('Template not found', 404);
        }

        return $this->response(success: true, message: 'Template retrieved successfully', data: MessengerTemplateResultDTO::fromModel($template)->toArray());
    }

    public function update(UpdateMessengerTemplateRequest $request, Channel $channel, string $templateId): JsonResponse
    {
        $metaPageId = $this->getMetaPageId($channel);
        if (!$metaPageId) {
            return $this->errorResponse('Messenger configuration not found for this channel');
        }

        $template = $this->templateService->find($metaPageId, $templateId);
        if (!$template) {
            return $this->errorResponse('Template not found', 404);
        }

        $dto = UpdateMessengerTemplateDTO::fromRequest(
            $request->validated(),
            $request->file('images'),
            $request->file('media_file'),
            $request->file('coupon_image')
        );
        $result = $this->updateAction->execute($template, $dto);

        return $this->response(success: true, message: 'Template updated successfully', data: $result->toArray());
    }

    public function destroy(Channel $channel, string $templateId): JsonResponse
    {
        $metaPageId = $this->getMetaPageId($channel);
        if (!$metaPageId) {
            return $this->errorResponse('Messenger configuration not found for this channel');
        }

        $template = $this->templateService->find($metaPageId, $templateId);
        if (!$template) {
            return $this->errorResponse('Template not found', 404);
        }

        $this->deleteAction->execute($template);

        return $this->response(success: true, message: 'Template deleted successfully', data: null);
    }

    public function toggleActive(Channel $channel, string $templateId): JsonResponse
    {
        $metaPageId = $this->getMetaPageId($channel);
        if (!$metaPageId) {
            return $this->errorResponse('Messenger configuration not found for this channel');
        }

        $template = $this->templateService->find($metaPageId, $templateId);
        if (!$template) {
            return $this->errorResponse('Template not found', 404);
        }

        $result = $this->toggleActiveAction->execute($template);

        return $this->response(
            success: true,
            message: $result->isActive ? 'Template activated' : 'Template deactivated',
            data: $result->toArray()
        );
    }

    public function duplicate(Channel $channel, string $templateId): JsonResponse
    {
        $metaPageId = $this->getMetaPageId($channel);
        if (!$metaPageId) {
            return $this->errorResponse('Messenger configuration not found for this channel');
        }

        $template = $this->templateService->find($metaPageId, $templateId);
        if (!$template) {
            return $this->errorResponse('Template not found', 404);
        }

        $result = $this->duplicateAction->execute($template);

        return $this->response(success: true, message: 'Template duplicated successfully', data: $result->toArray(), statusCode: 201);
    }

    public function preview(Channel $channel, string $templateId): JsonResponse
    {
        $metaPageId = $this->getMetaPageId($channel);
        if (!$metaPageId) {
            return $this->errorResponse('Messenger configuration not found for this channel');
        }

        $template = $this->templateService->find($metaPageId, $templateId);
        if (!$template) {
            return $this->errorResponse('Template not found', 404);
        }

        return $this->response(success: true, message: 'Template preview', data: $this->templateService->preview($template));
    }
}
