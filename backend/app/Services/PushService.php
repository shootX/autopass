<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PushService
{
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

    public function send() : void
    {
        $georgianTitle = $this->localized($this->title, 'ka');
        $georgianText = $this->localized($this->text, 'ka');

        $fields = [
            'app_id' => config('OneSignal.app_id'),
            'contents' => [
                'en' => $this->localized($this->text, 'en'),
                'ru' => $this->localized($this->text, 'ru'),
                'ka' => $georgianText,
                'ge' => $georgianText,
            ],
            'target_channel' => 'push',
            'include_subscription_ids' => [
                $this->device
            ],
            'headings' => [
                'en' => $this->localized($this->title, 'en'),
                'ru' => $this->localized($this->title, 'ru'),
                'ka' => $georgianTitle,
                'ge' => $georgianTitle,
            ],
            'data' => $this->data
        ];

        $req = Http::withHeaders([
            'Authorization' => 'Key ' . config('OneSignal.rest_api_key'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.onesignal.com/notifications?c=push', $fields);

        $response = $req->getBody();

        file_put_contents('push.log', $response, FILE_APPEND);
    }

    private function localized(array $source, string $code): string
    {
        if ($code === 'ka') {
            return (string) ($source['ka'] ?? $source['ge'] ?? $source['en'] ?? '');
        }

        return (string) ($source[$code] ?? $source['en'] ?? '');
    }
}
