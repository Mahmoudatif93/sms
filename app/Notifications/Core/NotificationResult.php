<?php

namespace App\Notifications\Core;

use Carbon\Carbon;
use Exception;

class NotificationResult
{
    protected bool $success;
    protected ?string $messageId;
    protected string $channel;
    protected ?string $recipient;
    protected ?string $error;
    protected array $data;
    protected Carbon $timestamp;
    protected ?string $externalId;
    protected array $metadata;
    protected ?int $retryCount;
    protected ?Carbon $nextRetryAt;

    public function __construct(
        bool $success,
        string $channel,
        ?string $recipient = null,
        ?string $messageId = null
    ) {
        $this->success = $success;
        $this->channel = $channel;
        $this->recipient = $recipient;
        $this->messageId = $messageId;
        $this->timestamp = Carbon::now();
        $this->data = [];
        $this->metadata = [];
        $this->retryCount = 0;
        $this->externalId = null;
        $this->error = null;
        $this->nextRetryAt = null;
    }

    public static function success(
        string $channel,
        ?string $recipient = null,
        ?string $messageId = null
    ): self {
        return new self(true, $channel, $recipient, $messageId);
    }

    public static function failure(
        string $channel,
        string $error,
        ?string $recipient = null
    ): self {
        $result = new self(false, $channel, $recipient);
        $result->setError($error);
        return $result;
    }

    public static function fromException(
        string $channel,
        Exception $exception,
        ?string $recipient = null
    ): self {
        $result = new self(false, $channel, $recipient);
        $result->setError($exception->getMessage());
        $result->addMetadata('exception_class', get_class($exception));
        $result->addMetadata('exception_code', $exception->getCode());
        $result->addMetadata('exception_file', $exception->getFile());
        $result->addMetadata('exception_line', $exception->getLine());
        return $result;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(?string $recipient): self
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;
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

    public function getTimestamp(): Carbon
    {
        return $this->timestamp;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
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

    public function getRetryCount(): ?int
    {
        return $this->retryCount;
    }

    public function setRetryCount(?int $retryCount): self
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    public function incrementRetryCount(): self
    {
        $this->retryCount = ($this->retryCount ?? 0) + 1;
        return $this;
    }

    public function getNextRetryAt(): ?Carbon
    {
        return $this->nextRetryAt;
    }

    public function setNextRetryAt(?Carbon $nextRetryAt): self
    {
        $this->nextRetryAt = $nextRetryAt;
        return $this;
    }

    public function scheduleRetry(int $delayMinutes): self
    {
        $this->nextRetryAt = Carbon::now()->addMinutes($delayMinutes);
        return $this;
    }

    public function canRetry(): bool
    {
        return $this->isFailure() && 
               $this->nextRetryAt !== null && 
               $this->nextRetryAt->isPast();
    }

    public function shouldRetry(int $maxRetries = 3): bool
    {
        return $this->isFailure() && 
               ($this->retryCount ?? 0) < $maxRetries;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message_id' => $this->messageId,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'error' => $this->error,
            'data' => $this->data,
            'timestamp' => $this->timestamp->toISOString(),
            'external_id' => $this->externalId,
            'metadata' => $this->metadata,
            'retry_count' => $this->retryCount,
            'next_retry_at' => $this->nextRetryAt?->toISOString(),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function __toString(): string
    {
        $status = $this->success ? 'SUCCESS' : 'FAILURE';
        $info = "[$status] {$this->channel}";
        
        if ($this->recipient) {
            $info .= " -> {$this->recipient}";
        }
        
        if ($this->messageId) {
            $info .= " (ID: {$this->messageId})";
        }
        
        if ($this->error) {
            $info .= " - Error: {$this->error}";
        }
        
        return $info;
    }
}
