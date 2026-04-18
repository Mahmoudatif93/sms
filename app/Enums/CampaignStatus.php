<?php

namespace App\Enums;
class CampaignStatus {
    const DRAFT = 'Draft';
    const SCHEDULED = 'Scheduled';
    const SENT = 'Sent';
    const COMPLETED = 'Completed';

    const PROGRESS = 'in_progress';


    public static function getKeyByValue($value) {
        $constants = (new \ReflectionClass(__CLASS__))->getConstants();
        return array_search($value, $constants) !== false ? array_search($value, $constants) : null;
    }
}
