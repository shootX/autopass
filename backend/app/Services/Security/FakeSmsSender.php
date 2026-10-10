<?php

namespace App\Services\Security;

class FakeSmsSender implements SmsSender
{
    /** @var list<array{destination: string, content: string}> */
    public array $messages = [];

    public bool $fail = false;

    public function send(string $destination, string $content): void
    {
        if ($this->fail) {
            throw new SmsDeliveryException('SMS delivery failed');
        }

        $this->messages[] = [
            'destination' => $destination,
            'content' => $content,
        ];
    }

    public function latestCode(): ?string
    {
        if ($this->messages === []) {
            return null;
        }

        $content = $this->messages[array_key_last($this->messages)]['content'];
        if (! preg_match('/(\d{6})/', $content, $matches)) {
            return null;
        }

        return $matches[1];
    }

    public function reset(): void
    {
        $this->messages = [];
        $this->fail = false;
    }
}
