<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class send_mail extends Mailable
{
    use Queueable, SerializesModels;

    public $details;
    public $viewName;

    /**
     * Create a new message instance.
     */
    public function __construct($details,$viewName)
    {
        $this->details = $details;
        $this->viewName = $viewName;

    }

  
    public function build()
    {
        $email = $this->subject($this->details['subject']);

        if ($this->viewName) {
            // Use the provided view
            $email->view($this->viewName, ['details' => $this->details]);
        } else {
            // Use raw text or HTML content directly
            $email->html($this->details['body']);
        }

        // Add attachments
        if (!empty($this->details['attachments'])) {
            foreach ($this->details['attachments'] as $filePath => $fileName) {
                $email->attach($filePath, ['as' => $fileName]);
            }
        }

        // Add inline attachments
        if (!empty($this->details['inline_attachments'])) {
            foreach ($this->details['inline_attachments'] as $filePath => $fileId) {
                $email->attachFromStorageDisk('public', $filePath, $fileId);
            }
        }

        // Add headers
        if (!empty($this->details['headers'])) {
            foreach ($this->details['headers'] as $headerName => $headerValue) {
                $email->withSwiftMessage(function ($message) use ($headerName, $headerValue) {
                    $message->getHeaders()->addTextHeader($headerName, $headerValue);
                });
            }
        }

        return $email;
    }
}
