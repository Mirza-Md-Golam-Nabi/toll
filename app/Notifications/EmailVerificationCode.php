<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously so the code arrives even on a booth without a queue worker.
 */
class EmailVerificationCode extends Notification
{
    public function __construct(public string $code, public int $expiresInMinutes) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your email verification code'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Use this code to verify your email address:'))
            ->line('**'.$this->code.'**')
            ->line(__('The code expires in :minutes minutes. If you did not request it, ignore this email.', ['minutes' => $this->expiresInMinutes]));
    }
}
