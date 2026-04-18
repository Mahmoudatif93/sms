<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;

class MailHelper
{
    /**
     * Configure mail settings dynamically.
     *
     * @param string $host
     * @param int $port
     * @param string $encryption
     * @param string $username
     * @param string $password
     * @param string $fromAddress
     * @param string $fromName
     * @return void
     */
    public static function configureMail($host, $port,  $username, $password, $fromAddress, $fromName)
    {
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', $fromName);
    }
}
