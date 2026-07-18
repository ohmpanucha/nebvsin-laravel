<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StorefrontPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $displayName;

    public $resetUrl;

    public $locale;

    public $expireMinutes;

    public function __construct(string $displayName, string $resetUrl, string $locale, int $expireMinutes)
    {
        $this->displayName = $displayName;
        $this->resetUrl = $resetUrl;
        $this->locale = $locale;
        $this->expireMinutes = $expireMinutes;
    }

    public function build()
    {
        $subject = $this->locale === 'th'
            ? 'รีเซ็ตรหัสผ่าน NEBVSIN'
            : 'Reset your NEBVSIN password';

        return $this->subject($subject)
            ->view('emails.storefront-password-reset');
    }
}
