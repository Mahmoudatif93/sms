<?php

namespace App\Notifications\Core;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Organization;
use Carbon\Carbon;

class NotificationMessage
{
    protected string $id;
    protected string $type;
    protected string $title = 'Dreams';
    protected string $content;
    protected array $data = [];
    protected array $recipients = [];
    protected array $channels = [];
    protected string $priority = 'normal';
    protected ?Carbon $scheduledAt = null;
    protected array $metadata = [];
    protected ?string $templateId = null;
    protected array $templateVariables = [];
    protected ?string $locale = null;
    protected ?User $sender = null;
    protected ?Workspace $workspace = null;
    protected ?Organization $organization = null;

    public function __construct(
        string $type,
        string $content,
        array $recipients = [],
        array $channels = [],
        string $priority = 'normal'
    ) {
        $this->id = $this->generateId();
        $this->type = $type;
        $this->content = $content;
        $this->recipients = $recipients;
        $this->channels = $channels;
        $this->priority = $priority;
        $this->locale = config('app.locale', 'ar');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): ?string
    {
        return $this->title ?? null;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function addData(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function setRecipients(array $recipients): self
    {
        $this->recipients = $recipients;
        return $this;
    }

    public function addRecipient(string $type, string $identifier, array $metadata = []): self
    {
        $this->recipients[] = [
            'type' => $type, // 'user', 'email', 'phone', 'telegram_chat'
            'identifier' => $identifier,
            'metadata' => $metadata
        ];
        return $this;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function addChannel(string $channel, array $options = []): self
    {
        $this->channels[$channel] = $options;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getScheduledAt(): ?Carbon
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?Carbon $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function scheduleFor(int $minutes): self
    {
        $this->scheduledAt = Carbon::now()->addMinutes($minutes);
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    public function setTemplateId(?string $templateId): self
    {
        $this->templateId = $templateId;
        return $this;
    }

    public function getTemplateVariables(): array
    {
        return $this->templateVariables;
    }

    public function setTemplateVariables(array $variables): self
    {
        $this->templateVariables = $variables;
        return $this;
    }

    public function addTemplateVariable(string $key, $value): self
    {
        $this->templateVariables[$key] = $value;
        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(?Workspace $workspace): self
    {
        $this->workspace = $workspace;
        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;
        return $this;
    }

    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null && $this->scheduledAt->isFuture();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'data' => $this->data,
            'recipients' => $this->recipients,
            'channels' => $this->channels,
            'priority' => $this->priority,
            'scheduled_at' => $this->scheduledAt?->toISOString(),
            'metadata' => $this->metadata,
            'template_id' => $this->templateId,
            'template_variables' => $this->templateVariables,
            'locale' => $this->locale,
            'sender_id' => $this->sender?->id,
            'workspace_id' => $this->workspace?->id,
            'organization_id' => $this->organization?->id,
        ];
    }

    protected function generateId(): string
    {
        return 'notif_' . uniqid() . '_' . time();
    }
}
