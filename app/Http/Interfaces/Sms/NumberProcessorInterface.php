<?php

namespace App\Http\Interfaces\Sms;

interface NumberProcessorInterface
{
    public function process($number, &$entries, $messageLong, &$numberArr,$message,$countries);
}
