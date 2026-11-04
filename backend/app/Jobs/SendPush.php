<?php

namespace App\Jobs;

use App\Models\UserPushToken;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPush implements ShouldQueue
{
    use Queueable;

    private $device;

    private $title = [];

    private $text = [];

    private $data = [];

    private ?array $recipients = null;

    public function __construct($device, $title = [], $text = [], $data = [], ?int $recipientUserId = null)
    {
        $this->device = $device;
        $this->title = $title;
        $this->text = $text;
        $this->data = $data;
        $this->recipients = UserPushToken::query()->whereIn('push_id', (array) $device)
            ->when($recipientUserId !== null, fn ($query) => $query->where('user_id', $recipientUserId))
            ->pluck('user_id', 'id')->all();
    }

    public function handle(): void
    {
        // Legacy jobs without a recipient snapshot cannot safely identify the account.
        if ($this->recipients === null || $this->recipients === []) {
            return;
        }
        (new PushService($this->device, $this->title, $this->text, $this->data, $this->recipients))->send();
    }
}
