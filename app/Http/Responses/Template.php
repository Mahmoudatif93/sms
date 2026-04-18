<?php

namespace App\Http\Responses;

class Template
{
    public string $id;
    public string $name;
    public string $language;
    public string $category;
    public string $status;
    public int|null $message_send_ttl_seconds;

    public function __construct($template)
    {
        $this->id = $template['id'];
        $this->name = $template['name'];
        $this->language = $template['language'];
        $this->category = $template['category'];
        $this->status = $template['status'];
        if(isset($template['message_send_ttl_seconds'])) {
            $this->message_send_ttl_seconds = $template['message_send_ttl_seconds'];
        }

    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
