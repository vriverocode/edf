<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

// --- ESTAS SON LAS 3 IMPORTACIONES QUE TE FALTAN ---
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
class RealtimeNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public array $meta = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'meta' => $this->meta,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
    public function toFcm($notifiable): FcmMessage
    {
        return FcmMessage::create()
            // CORRECCIÓN 1: Es 'data', no 'setData'
            ->data(['url' => $this->url ?? '']) 
            
            // CORRECCIÓN 2: Es 'notification', no 'setNotification'
            ->notification( 
                FcmNotification::create()
                    ->title($this->title)
                    ->body($this->message)
            );
    }
}
