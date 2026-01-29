<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RuleActionNotification extends Notification
{
    use Queueable;

    protected string $title;
    protected string $message;
    protected array $data;
    protected array $channels;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $message, array $data = [], array $channels = ['database'])
    {
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->channels = $channels;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->when(!empty($this->data), function ($mail) {
                foreach ($this->data as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $mail->line("{$key}: {$value}");
                    }
                }
            });
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'type' => 'rule_action',
        ];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'type' => 'rule_action',
        ];
    }
}
