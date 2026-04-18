<?php

namespace App\Traits;

trait ValidatesWhatsappComponents
{

    protected function validateHeaderParam(array $param): bool
    {
        return match ($param['type'] ?? null) {
            'text' => isset($param['text']),
            'image' => isset($param['image']['link']),
            'video' => isset($param['video']['link']),
            'document' => isset($param['document']['link']),
            'location' => isset($param['location']['latitude'], $param['location']['longitude']),
            default => false,
        };
    }
}
