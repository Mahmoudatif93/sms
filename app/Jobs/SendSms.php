<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\SmsSuccessEvent;
use App\Events\SmsFaileEvent;
use Illuminate\Support\Facades\Http;
use Throwable;
class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $from;
    protected $to;
    protected $message_text;
    protected $message_id;
    protected $is_hlr;
    protected $url;
    protected $gateway_id;
    protected $model;
    protected $is_variable;
    public $tries = 3;
    public function __construct($from,$to,$message_text,$message_id,$is_hlr,$url,$gateway_id,$model,$is_variable=0)
    {
        $this->from = $from;
        $this->to = $to;
        $this->message_text = $message_text;
        $this->message_id = $message_id;
        $this->is_hlr = $is_hlr;
        $this->url = $url;
        $this->gateway_id = $gateway_id;
        $this->model = $model;
        $this->is_variable = $is_variable;
    }
    //TODO: must uniq in message_id 
    /**
     * Execute the job.
     */
    public function handle(): void
    {   
        $to = $this->PrepareNumber($this->to,$this->is_variable);
        $url = str_replace(
            ['*USERNAME*', '*PASSWORD*', '*MESSAGE*', '*MOBILENO*', '*SENDERID*', '*MESSAGEID*', '*IS_HLR*'],
            ['Dreams', '123456', urlencode(trim($this->message_text)), $to, $this->from, $this->message_id, $this->is_hlr],
            $this->url
        );

        $url_parts = explode("?", $url, 2);
        parse_str($url_parts[1],$queryArray);
        $response = Http::post($url_parts[0],$queryArray);
        $response->throw();
        if($response->successful() == 200){
            event(new SmsSuccessEvent($this->message_id,$this->to,$this->gateway_id,$this->model,$response->body()));
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        event(new SmsFaileEvent($this->message_id,$this->to,$this->model));
    }

    protected function PrepareNumber($to,$is_variable){
        return $is_variable ? json_encode($to):implode(',', $this->to);
        
    }
}
