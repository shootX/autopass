<?php

namespace App\Services\Sms;

class MockSmsGateway implements SmsGateway
{
    /** @var list<array{destination: string, content: string, purpose: string, reference: string}> */
    public array $messages = [];

    public function send(SmsMessage $message): SmsResult
    {
        $this->messages[] = [
            'destination' => $message->destination,
            'content' => $message->content,
            'purpose' => $message->purpose,
            'reference' => $message->reference,
        ];

        return new SmsResult(SmsStatus::Simulated, $message->reference);
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
    }
}
