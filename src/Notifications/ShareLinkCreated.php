<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class ShareLinkCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ShareLink $link) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        /** @var array<int, string> $channels */
        $channels = (array) config('media-library.notifications.channels', ['mail', 'database']);

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A media library item has been shared with you')
            ->view('media-library::notifications.share-link-created', ['link' => $this->link]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'share_link_id' => $this->link->id,
            'token' => $this->link->token,
            'item_id' => $this->link->item_id,
            'folder_id' => $this->link->folder_id,
            'expires_at' => $this->link->expires_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
