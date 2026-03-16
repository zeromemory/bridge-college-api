<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationRejectedNotification extends Notification
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
        $mail = (new MailMessage)
            ->subject('Application Update — Bridge College International')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your application **' . $this->application->application_number . '** has been reviewed.')
            ->line('Unfortunately, your application was not accepted at this time.');

        if ($this->application->admin_notes) {
            $mail->line('**Reason:** ' . $this->application->admin_notes);
        }

        $mail->line('If you have any questions, please contact our administration office.')
            ->line('Thank you for your interest in Bridge College International.');

        return $mail;
    }
}
