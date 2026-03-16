<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification
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
        $branchName = $this->application->branch?->name ?? 'N/A';

        return (new MailMessage)
            ->subject('Application Submitted — Bridge College International')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your application has been submitted successfully.')
            ->line('**Application Number:** ' . $this->application->application_number)
            ->line('**Program:** ' . $programName)
            ->line('**Branch:** ' . $branchName)
            ->line('We will review your application and notify you of the decision shortly.')
            ->line('Thank you for choosing Bridge College International!');
    }
}
