<?php

namespace App\Traits;

use App\Traits\ValidatesWhatsappComponents;

trait HeaderComponentBuilder
{
    use ValidatesWhatsappComponents;

    public function buildHeaderComponent(array $templateComponent, ?array $requestComponent): ?array
    {
        $parameters = $requestComponent['parameters'] ?? [];
        if (empty($parameters)) {
            return ['success' => true, 'component' => null ];
        }

        $validated = [];
        foreach ($parameters as $param) {
            $type = $param['type'] ?? null;

            if (!$type) {
                return ['success' => false, 'error' => 'Missing parameter type in header'];
            }

            if (!$this->validateHeaderParam($param)) {
                return ['success' => false, 'error' => "Invalid header parameter structure"];
            }
            $validated[] = match ($type) {
                'text'     => ['type' => 'text', 'text' => $param['text']],
                'image'    => ['type' => 'image', 'image' => ['link' => $param['image']['link']]],
                'video'    => ['type' => 'video', 'video' => ['link' => $param['video']['link']]],
                'document' => ['type' => 'document', 'document' => ['link' => $param['document']['link']]],
                'location' => ['type' => 'location', 'location' => $param['location']],
                default    => null,
            };
        }

        return ['success' => true, 'component' => ['type' => 'header', 'parameters' => $validated]];
    }
}
