<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public ?string $url = null,
        public array $meta = []
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $user->notify(new RealtimeNotification(
            title: $this->title,
            message: $this->message,
            url: $this->url,
            meta: $this->meta,
        ));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendNotificationJob failed: '.$exception->getMessage());
    }
}
