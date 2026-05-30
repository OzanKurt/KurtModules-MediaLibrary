<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class ShareLinkAccessed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ShareLink $link,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {}

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
            ->subject('Your shared media library link was accessed')
            ->view('media-library::notifications.share-link-accessed', [
                'link' => $this->link,
                'ip' => $this->ip,
                'userAgent' => $this->userAgent,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'share_link_id' => $this->link->id,
            'token' => $this->link->token,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'accessed_at' => now()->toIso8601String(),
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
