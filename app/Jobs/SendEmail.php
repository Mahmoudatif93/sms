<?php

namespace App\Jobs;

use App\Mail\send_mail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $to;
    public $subject;
    public $title;
    public $body;
    public $viewName;
    public $attachments;
    public $inline_attachments;
    public $cc;
    public $bcc;
    public $message;
    public $port;
    public $password;
    public $otpType;

    public function __construct($to, $subject, $title, $body, $viewName = null, $attachments = [],
                                $inline_attachments = [], $cc = [], $bcc = [], $message = null, $otpType = null)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->title = $title;
        $this->body = $body;
        $this->attachments = $attachments;
        $this->inline_attachments = $inline_attachments;
        $this->cc = $cc;
        $this->bcc = $bcc;
        $this->message = $message;
        $this->viewName = $viewName;
        $this->otpType = $otpType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $details = [
            'subject' => $this->subject,
            'title' => $this->title,
            'body' => $this->body,
            'attachments' => $this->attachments,
            'inline_attachments' => $this->inline_attachments,
            'message' => $this->message,
            'otp_type' => $this->otpType,
            'headers' => [
                'X-Custom-Header' => 'Custom Header Value'
            ]
        ];
        $email = Mail::to($this->to);

        if (!empty($cc)) {
            $email->cc($cc);
        }

        if (!empty($bcc)) {
            $email->bcc($bcc);
        }
        $email->send(new send_mail($details, $this->viewName));
    }
}
