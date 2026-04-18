<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use InvalidArgumentException;

class UrlButton
{
    private string $type = 'URL';
    private string $text;
    private string $url;
    private ?array $example;

    public function __construct(string $text, string $url, ?array $example = null)
    {
        if (mb_strlen($text) > 25) {
            throw new InvalidArgumentException('Button text cannot exceed 25 characters.');
        }
        if (mb_strlen($url) > 2000) {
            throw new InvalidArgumentException('URL cannot exceed 2000 characters.');
        }

        // Check for variable usage
        preg_match_all('/{{\d+}}/', $url, $matches);
        $placeholders = $matches[0];

        if (!empty($placeholders)) {
            if (count($placeholders) > 1) {
                throw new InvalidArgumentException('URL can contain only one variable placeholder (e.g., {{1}}).');
            }

            if ($placeholders[0] !== '{{1}}') {
                throw new InvalidArgumentException('URL must use {{1}} as the only allowed placeholder.');
            }

            if (empty($example) || !is_array($example) || count($example) !== 1 || !is_string($example[0])) {
                throw new InvalidArgumentException('URL placeholder {{1}} requires a corresponding example in the form: ["value"].');
            }
        }

        $this->text = $text;
        $this->url = $url;
        $this->example = $example;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getExample(): ?array
    {
        return $this->example;
    }

    public function toArray(): array
    {
        $buttonArray = [
            'type' => $this->type,
            'text' => $this->text,
            'url' => $this->url,
        ];

        if ($this->example !== null) {
            $buttonArray['example'] = $this->example;
        }

        return $buttonArray;
    }
}
