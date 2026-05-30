<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class LargeUploadCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly MediaLibraryItem $item) {}

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
            ->subject('Your upload has finished processing')
            ->view('media-library::notifications.large-upload-completed', [
                'item' => $this->item,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'item_id' => $this->item->id,
            'filename' => $this->item->filename,
            'byte_size' => $this->item->byte_size,
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
