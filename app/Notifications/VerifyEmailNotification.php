<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        $parsed = parse_url($signedUrl);
        parse_str($parsed['query'] ?? '', $queryParams);

        return config('app.frontend_url') . '/portal/verify-email?' . http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $queryParams['expires'] ?? '',
            'signature' => $queryParams['signature'] ?? '',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Email — Bridge College International')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Thank you for registering with Bridge College International.')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $this->verificationUrl($notifiable))
            ->line('This link will expire in 60 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}
