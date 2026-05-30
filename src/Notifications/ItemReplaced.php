<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class ItemReplaced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly MediaLibraryItem $item,
        public readonly int $previousSpatieMediaId,
        public readonly ?string $changelog = null,
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
            ->subject('A media library item you follow was replaced')
            ->view('media-library::notifications.item-replaced', [
                'item' => $this->item,
                'changelog' => $this->changelog,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'item_id' => $this->item->id,
            'previous_spatie_media_id' => $this->previousSpatieMediaId,
            'changelog' => $this->changelog,
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
