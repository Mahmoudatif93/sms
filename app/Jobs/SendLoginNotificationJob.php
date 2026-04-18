<?php

namespace App\Jobs;

use App\Mail\LoginNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Settings\EmailController;

class SendLoginNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $emailController;
    protected $email;
    protected $body;
    protected $message;
    /**
     * Create a new job instance.
     */
    public function __construct( EmailController $emailController,$email,$body,$message)
    {
        $this->emailController = $emailController;
        $this->email = $email;
        $this->body = $body;
        $this->message=$message;

    }


    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->emailController->sendEmail($this->email, $this->message, "Dreams SMS", $this->body);
    }
}
