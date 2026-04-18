<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use App\Models\Setting;
use App\Services\Sms;
use Carbon\Carbon;

class SendSmsLater extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:send-sms-later';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'send Sms later';

    /**
     * Execute the console command.
     */
    public function handle()
    {
       
        // dd(Carbon::now());
        $messages = Message::where('status',0)
        ->where('sending_datetime','<=', Carbon::now()->subMinutes(Setting::get_by_name('offset_time'))
        )->where('advertising', '<>', 1)->get();
        foreach ($messages as $message) {
            Sms::sendCampaign($message->id,$message->variables_message,0);
        }
    }
}   
