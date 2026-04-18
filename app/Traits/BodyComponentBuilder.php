<?php

namespace App\Traits;

use Exception;

trait BodyComponentBuilder
{
    use ValidatesWhatsappComponents;

    public function buildBodyComponent(array $templateComponent, ?array $requestComponent): array
    {
        $example = $templateComponent['example']['body_text'][0] ?? [];
        $parameters = $requestComponent['parameters'] ?? [];

        // No variables — plain text body
        if (empty($example) || empty($parameters)) {
            return ['success' => true, 'component' => null];
        }

        if (count($parameters) !== count($example)) {
            return ['success' => false, 'error' => 'Body parameter count mismatch'];
        }

        $built = [];
        foreach ($parameters as $param) {
            try {
                $paramObj = $this->buildParameter($param);
                $paramObj->validate();
                $built[] = $paramObj->toArray();
            } catch (Exception $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return ['success' => true, 'component' => ['type' => 'body', 'parameters' => $built]];
    }
}
