<?php

namespace App\Jobs;

use App\Mail\UserInvitationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class SendUserInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $activation_link;
    public string $email;

    /**
     * Create a new job instance.
     */
    public function __construct(string $email, string $activation_link)
    {
        $this->email = $email;
        $this->activation_link = $activation_link;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Send the email using the Mailable class
        Mail::to($this->email)->send(new UserInvitationMail($this->activation_link));
    }
}
