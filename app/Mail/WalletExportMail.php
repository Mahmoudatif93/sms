<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WalletExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $downloadUrl;
    public string $userLocale;

    public function __construct(string $downloadUrl, string $userLocale = 'en')
    {
        $this->downloadUrl = $downloadUrl;
        $this->userLocale = $userLocale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('message.export_subject'),
        );
    }

    public function content(): Content
    {
        $view = $this->userLocale === 'ar'
            ? 'mail.notifications.wallet_export_ar'
            : 'mail.notifications.wallet_export_en';

        return new Content(
            view: $view,
            with: [
                'downloadUrl' => $this->downloadUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
