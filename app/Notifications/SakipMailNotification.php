<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SakipMailNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Yth. Pengguna E-SAKIP,')
            ->line($this->message);

        if ($this->url) {
            $mail->action('Buka E-SAKIP', $this->url);
        }

        return $mail->line('Email ini dikirim otomatis oleh E-SAKIP Kabupaten Banjarnegara.');
    }
}
