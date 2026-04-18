<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\send_mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;

class EmailController extends Controller
{
    public function sendEmail(
        string $to,
        string $subject,
        string $title,
        string $body,
        string $viewName = "",
        array $attachments = [],
        array $inline_attachments = [],
        array $cc = [],
        array $bcc = [],
        ?string $message = null,
        ?string $mailHost = null,
        ?int $port = null,
        ?string $fromEmail = null,
        ?string $password = null,
        $from_name = null,
        $site_name = null
    ) {

        $mailHost = Setting::getValueByName('smtp_host');
        $port = Setting::getValueByName('smtp_port');
        $fromEmail = Setting::getValueByName('site_email');
        $password = Setting::getValueByName('smtp_pass');
        $site_name = Setting::getValueByName('site_name');
        // Use database values if available, otherwise fall back to function parameters
        $mailHost = $mailHost ?? $mailHost;
        $port = $port ?? $port;
        $fromEmail = $fromEmail ?? $fromEmail;
        $password = $password ?? $password;

        if (!$mailHost || !$port || !$fromEmail || !$password) {
            throw new \Exception("Mail configuration is incomplete. Ensure all required parameters are provided.");
        }
        // Configure the mail settings dynamically
        config([
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $mailHost,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.encryption' => 'tls', // Change if you use a different encryption
            'mail.mailers.smtp.username' => $fromEmail,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $fromEmail,
            'mail.from.name' => $site_name // Change to your desired sender name
        ]);

        $details = [
            'subject' => $subject,
            'title' => $title,
            'body' => $body,
            'attachments' => $attachments,
            'inline_attachments' => $inline_attachments,
            'message' => $message,
            'headers' => [
                'X-Custom-Header' => 'Custom Header Value'
            ]
        ];

        $email = Mail::to($to);

        if (!empty($cc)) {
            $email->cc($cc);
        }

        if (!empty($bcc)) {
            $email->bcc($bcc);
        }
        if ($email->send(new send_mail($details, $viewName))) {
            return "Email Sent!";
        }
    }
}
