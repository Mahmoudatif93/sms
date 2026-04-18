<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Http\Interfaces\TemplateComponent;
use InvalidArgumentException;

class HeaderTextComponent implements TemplateComponent
{
    /**
     * @var string
     */
    private string $type = 'HEADER';
    /**
     * @var string
     */
    private string $format;
    /**
     * @var string
     */
    private string $text;
    /**
     * @var array|mixed|null
     */
    private array|null $example;

    public function __construct($format, $text, $example = null)
    {
        $this->format = $format;
        $this->text = $text;
        $this->example = $example;

        $this->validateFormat($format);
        $this->validateText($text, $example);
    }

    public function toJson(): array
    {
        $component = [
            'type' => $this->type,
            'format' => $this->format,
            'text' => $this->text,
        ];

        if (!empty($this->example)) {
            $component['example'] = $this->example;
        }

        return $component;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getExample(): array|null
    {
        return $this->example;
    }
    public function getType(): string
    {
        return $this->type;
    }

    private function validateFormat(string $format): void
    {
        $allowedFormats = ['TEXT', 'LOCATION', 'IMAGE', 'VIDEO', 'DOCUMENT'];
        if (!in_array($format, $allowedFormats)) {
            throw new InvalidArgumentException("Invalid format: $format. Allowed formats are: " . implode(', ', $allowedFormats));
        }
    }

    private function validateText(string $text, ?array $example): void
    {
        // Check length first
        if (mb_strlen($text) > 60) {
            throw new InvalidArgumentException('Header text exceeds maximum length of 60 characters.');
        }

        // Match all positional variables: {{1}}, {{2}}, etc.
        preg_match_all('/{{(\d+)}}/', $text, $matches);
        $placeholders = $matches[1]; // Extract just the number part, e.g., ['1']

        // No variables: nothing to validate further
        if (empty($placeholders)) {
            return;
        }

        // Must contain only one placeholder
        if (count($placeholders) > 1) {
            throw new InvalidArgumentException("Header text can contain only one variable {{1}}.");
        }

        // That placeholder must be {{1}} exactly
        if ($placeholders[0] !== '1') {
            throw new InvalidArgumentException("Header text must use {{1}} as the only allowed variable.");
        }

        // Validate example presence
        if (
            $example === null ||
            empty($example['header_text'][0][0])
        ) {
            throw new InvalidArgumentException("Missing example value for {{1}} in 'header_text' example.");
        }
    }
}
