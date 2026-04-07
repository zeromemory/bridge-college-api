<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeacherAccountSetupNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = config('app.frontend_url') . '/portal/teacher/setup?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Welcome to Bridge College International — Set up your teacher account')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A Bridge College International administrator has created a teacher account for you.')
            ->line('To get started, please click the button below to set your password and activate your account.')
            ->action('Set Up My Account', $url)
            ->line('This setup link will expire in 24 hours.')
            ->line('If the link has expired, please contact your administrator to receive a new one, or use the "Forgot Password" option on the login page.')
            ->salutation('— Bridge College International');
    }
}
