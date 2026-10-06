<?php

namespace App\Jobs;

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

    public function __construct($device, $title = [], $text = [], $data = [])
    {
        $this->device = $device;
        $this->title = $title;
        $this->text = $text;
        $this->data = $data;
    }

    public function handle(): void
    {
        (new PushService($this->device, $this->title, $this->text, $this->data))->send();
    }
}
