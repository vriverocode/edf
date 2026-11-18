<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RegistrationStatus;
use App\Mail\ResidentOnboardingMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendOnboardingMagicLinkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(): void
    {
        $user = User::with('urbanismo')->find($this->userId);

        if (! $user || $user->estado_registro !== RegistrationStatus::Pendiente) {
            return;
        }

        $user->activationTokens()->delete();

        $plainToken = Str::random(64);

        $user->activationTokens()->create([
            'purpose' => 'activation',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        $url = rtrim((string) config('app.frontend_url'), '/')
            .'/activar-cuenta?'.http_build_query([
                'token' => $plainToken,
                'email' => $user->email,
            ]);

        Mail::to($user->email)->send(new ResidentOnboardingMail($user, $url));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendOnboardingMagicLinkJob failed: '.$exception->getMessage(), [
            'user_id' => $this->userId,
        ]);
    }
}
