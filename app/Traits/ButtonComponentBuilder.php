<?php

namespace App\Traits;

trait ButtonComponentBuilder
{
    use ValidatesWhatsappComponents;

    /**
     * Build all button components for a BUTTONS template component.
     * Meta allows multiple buttons inside one component (buttons[0], buttons[1], ...)
     */
    public function buildButtonComponent(
        array $templateComponent,
        ?array $requestComponent,
        int $buttonIndex
    ): array
    {
        // Extract template button
        $buttonTemplate = $templateComponent['buttons'][$buttonIndex] ?? null;
        if (!$buttonTemplate) {
            return ['success' => false, 'error' => "Button index {$buttonIndex} not found in template"];
        }

        $subType = strtolower($buttonTemplate['type'] ?? '');

        // Template example (only dynamic buttons have this)
        $templateExample = $buttonTemplate['example'][0] ?? null;

        // Extract request parameter
        $param = $requestComponent['buttons'][$buttonIndex]['parameters'][0] ?? null;

        /**
         * CASE 1:
         * Static button → no example → skip
         */
        if (!$templateExample) {
            return [
                'success' => true,
                'component' => null
            ];
        }

        /**
         * CASE 2:
         * Example exists → dynamic button → parameter REQUIRED
         * If none supplied → fallback to example
         */
        if (!$param) {
            $param = $this->buildFallbackParamFromExample($subType, $templateExample);
        }

        // Build the button
        return match ($subType) {
            'url'        => $this->buildUrlButton($buttonIndex, $param),
            'copy_code'  => $this->buildCopyCodeButton($buttonIndex, $param),
            'quick_reply'=> $this->buildQuickReplyButton($buttonIndex, $param),
            default      => [
                'success' => false,
                'error'   => "Unsupported button type: {$subType}"
            ],
        };
    }



    /**
     * Fallback builder: creates param object from example when user does NOT send params
     */
    protected function buildFallbackParamFromExample(string $subType, string $example): array
    {
        return match ($subType) {
            'url'        => ['type' => 'text', 'text' => $example],
            'quick_reply'=> ['type' => 'payload', 'payload' => $example],
            'copy_code'  => ['type' => 'copy_code', 'copy_code' => $example],
            default      => throw new \Exception("Unknown subtype {$subType}"),
        };
    }


    protected function buildQuickReplyButton(int $index, array $param): array
    {
        $payload = $param['payload'] ?? null;
        if (!$payload) return ['success' => false, 'error' => 'Missing payload for quick_reply'];

        return [
            'success' => true,
            'component' => [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => $index,
                'parameters' => [
                    ['type' => 'payload', 'payload' => $payload]
                ],
            ]
        ];
    }


    protected function buildUrlButton(int $index, array $param): array
    {
        $text = $param['text'] ?? null;
        if (!$text) return ['success' => false, 'error' => 'Missing text for URL button'];

        return [
            'success' => true,
            'component' => [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => $index,
                'parameters' => [
                    ['type' => 'text', 'text' => $text]
                ],
            ]
        ];
    }


    protected function buildCopyCodeButton(int $index, array $param): array
    {
        $code = $param['copy_code'] ?? null;

        if (!$code || mb_strlen($code) > 15) {
            return ['success' => false, 'error' => 'Invalid or too long copy_code'];
        }

        return [
            'success' => true,
            'component' => [
                'type' => 'button',
                'sub_type' => 'copy_code',
                'index' => $index,
                'parameters' => [
                    ['type' => 'coupon_code', 'coupon_code' => $code]
                ],
            ]
        ];
    }
}
