<?php

namespace App\Domain\Chatbot\DTOs;

class ImportResultDTO
{
    public function __construct(
        public readonly int $imported,
        public readonly int $updated,
        public readonly int $failed,
        public readonly array $errors = [],
    ) {}

    public static function success(int $imported, int $updated = 0): self
    {
        return new self(
            imported: $imported,
            updated: $updated,
            failed: 0,
            errors: [],
        );
    }

    public static function partial(int $imported, int $updated, int $failed, array $errors): self
    {
        return new self(
            imported: $imported,
            updated: $updated,
            failed: $failed,
            errors: $errors,
        );
    }

    public static function failed(array $errors): self
    {
        return new self(
            imported: 0,
            updated: 0,
            failed: count($errors),
            errors: $errors,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->failed === 0;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getTotalProcessed(): int
    {
        return $this->imported + $this->updated + $this->failed;
    }

    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'errors' => $this->errors,
            'total_processed' => $this->getTotalProcessed(),
        ];
    }
}
