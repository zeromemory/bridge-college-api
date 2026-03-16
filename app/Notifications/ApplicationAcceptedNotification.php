<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Application $application,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $programName = $this->application->program?->name ?? 'N/A';

        $mail = (new MailMessage)
            ->subject('Application Accepted — Bridge College International')
            ->greeting('Congratulations, ' . $notifiable->name . '!')
            ->line('Your application **' . $this->application->application_number . '** has been accepted.')
            ->line('**Program:** ' . $programName);

        if ($this->application->admin_notes) {
            $mail->line('**Note from Administration:** ' . $this->application->admin_notes);
        }

        $mail->line('Please log in to your portal for further updates.')
            ->action('Go to Portal', config('app.frontend_url') . '/portal/dashboard')
            ->line('Welcome to Bridge College International!');

        return $mail;
    }
}
