<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use InvalidArgumentException;

class FlowButton
{
    private string $type = 'FLOW';
    private string $text;
    private ?string $flowId;
    private ?string $flowJson;
    private string $flowAction;
    private ?string $navigateScreen;

    public function __construct(string $text, ?string $flowId, ?string $flowJson, string $flowAction = 'navigate', ?string $navigateScreen = null)
    {
        if (mb_strlen($text) > 25) {
            throw new InvalidArgumentException('Flow button text cannot exceed 25 characters.');
        }

        if ($flowId && $flowJson) {
            throw new InvalidArgumentException('Cannot use both flow_id and flow_json at the same time.');
        }

        $this->text = $text;
        $this->flowId = $flowId;
        $this->flowJson = $flowJson;
        $this->flowAction = $flowAction;
        $this->navigateScreen = $navigateScreen;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getNavigateScreen(): ?string
    {
        return $this->navigateScreen;
    }

    public function getFlowAction(): string
    {
        return $this->flowAction;
    }

    public function getFlowJson(): ?string
    {
        return $this->flowJson;
    }

    public function getFlowId(): ?string
    {
        return $this->flowId;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function toArray(): array
    {
        $buttonArray = [
            'type' => $this->type,
            'text' => $this->text,
            'flow_action' => $this->flowAction,
        ];

        if ($this->flowId !== null) {
            $buttonArray['flow_id'] = $this->flowId;
        }

        if ($this->flowJson !== null) {
            $buttonArray['flow_json'] = $this->flowJson;
        }

        if ($this->navigateScreen !== null) {
            $buttonArray['navigate_screen'] = $this->navigateScreen;
        }

        return $buttonArray;
    }
}
