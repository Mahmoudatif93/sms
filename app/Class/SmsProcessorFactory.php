<?php
namespace App\Class;
use App\Models\Setting;
use App\Services\Sms;
 class SmsProcessorFactory {
    protected $sms;
    public function __construct(Sms $sms)
    {
        $this->sms = $sms;
    }
    public static function createProcessor($count) {
        $smsService = app(Sms::class); 
        if($count > Setting::get_by_name('real_time_send_limit')){
            return new LargeSmsProcessor($smsService);
        }else{
            return new SmallSmsProcessor($smsService);
        }
    }
}