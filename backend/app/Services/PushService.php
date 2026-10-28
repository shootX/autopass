<?php

namespace App\Services;

use App\Models\UserPushToken;
use App\Services\Push\ApnsClient;

/** One push to one or many devices, each in the language its app runs in. */
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

    public function send(): void
    {
        $ids = array_values(array_filter((array) $this->device, 'is_string'));
        if ($ids === []) {
            return;
        }
        $apns = app(ApnsClient::class);
        UserPushToken::query()->where('kind', UserPushToken::KIND_APNS)->whereIn('push_id', $ids)
            ->get()
            ->each(function (UserPushToken $token) use ($apns) {
                $lang = in_array($token->lang, ['ka', 'en', 'ru'], true) ? $token->lang : 'ka';
                $result = $apns->send(
                    $token->push_id,
                    $this->localized($this->title, $lang),
                    $this->localized($this->text, $lang),
                    (array) $this->data,
                    (bool) $token->sandbox
                );
                if ($result === ApnsClient::GONE) {
                    $token->delete();
                }
            });
    }

    private function localized(array $source, string $code): string
    {
        if ($code === 'ka') {
            return (string) ($source['ka'] ?? $source['ge'] ?? $source['en'] ?? '');
        }

        return (string) ($source[$code] ?? $source['en'] ?? '');
    }
}
