<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;

class ManagementNotifiable
{
    use Notifiable;

    protected $email;
    protected $lang;

    public function __construct($email, $lang = 'en')
    {
        $this->email = $email;
        $this->lang = $lang;
    }

    /**
     * Route notifications for the mail channel.
     *
     * @return string
     */
    public function routeNotificationForMail()
    {
        return $this->email;
    }

    /**
     * Get the language preference.
     *
     * @return string
     */
    public function getLangAttribute()
    {
        return  config('app.locale') ?? 'ar';
    }
}