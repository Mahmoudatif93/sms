<?php

namespace App\Http\Whatsapp\WhatsappFlows;

/**
 * @OA\Schema(
 *     schema="FlowValidationError",
 *     type="object",
 *     @OA\Property(property="error", type="string"),
 *     @OA\Property(property="error_type", type="string"),
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="line_start", type="integer"),
 *     @OA\Property(property="line_end", type="integer"),
 *     @OA\Property(property="column_start", type="integer"),
 *     @OA\Property(property="column_end", type="integer")
 * )
 */

class FlowValidationError
{
    public string $error;
    public string $error_type;
    public string $message;
    public ?int $line_start;
    public ?int $line_end;
    public ?int $column_start;
    public ?int $column_end;

    public function __construct(array $error)
    {
        $this->error = $error['error'] ?? $error['error_description'] ?? '';
        $this->error_type = $error['error_type'] ?? '';
        $this->message = $error['message'] ?? $error['error_description'] ?? '';
        $this->line_start = $error['line_start'] ??null;
        $this->line_end = $error['line_end'] ?? null;
        $this->column_start = $error['column_start'] ?? null;
        $this->column_end = $error['column_end'] ?? null;
    }
}
