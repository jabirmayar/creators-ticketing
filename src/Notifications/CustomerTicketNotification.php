<?php

namespace daacreators\CreatorsTicketing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class CustomerTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $ticketUid,
        public ?string $trackingUrl,
        public ?string $reply = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->reply === null ? 'received' : 'replied';
        $mail = (new MailMessage)
            ->subject(__('creators-ticketing::resources.mail.'.$key.'_subject', ['uid' => $this->ticketUid]))
            ->line(__('creators-ticketing::resources.mail.'.$key, ['uid' => $this->ticketUid]));

        if ($this->reply !== null) {
            // Plain text prevents rich editor HTML, images and private attachment URLs leaking into mail.
            $text = preg_replace('/<\s*(?:br\s*\/?|\/p|\/div|\/li)\s*>/i', "\n", $this->reply);
            $text = preg_replace('~https?://[^\s<>]+/(?:private/ticket-attachments|tickets/track)/[^\s<>]+~i', '', strip_tags($text));
            $mail->line(new HtmlString(nl2br(e(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')))));
        }

        if ($this->trackingUrl) {
            $mail->action(__('creators-ticketing::resources.mail.view_ticket'), $this->trackingUrl);
        }

        return $mail->line(__('creators-ticketing::resources.mail.reply_via_link'));
    }
}
