<?php

namespace App\Jobs;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendTelegramBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    /** @param list<array{label: string, url: string}> $buttons */
    public function __construct(
        public int $chatId,
        public string $type,
        public ?string $text,
        public ?string $mediaPath,
        public array $buttons = [],
    ) {}

    public function handle(): void
    {
        $chat = TelegraphChat::query()->findOrFail($this->chatId);

        $telegraph = match ($this->type) {
            'photo' => $chat->photo(Storage::disk('local')->path((string) $this->mediaPath)),
            'video' => $chat->video(Storage::disk('local')->path((string) $this->mediaPath)),
            'document' => $chat->document(Storage::disk('local')->path((string) $this->mediaPath)),
            default => $chat->message((string) $this->text),
        };

        if ($this->type !== 'text' && filled($this->text)) {
            $telegraph = $telegraph->withData('caption', $this->text);
        }

        if ($this->buttons !== []) {
            $keyboard = Keyboard::make();

            foreach ($this->buttons as $button) {
                $keyboard = $keyboard->row([Button::make($button['label'])->url($button['url'])]);
            }

            $telegraph = $telegraph->keyboard($keyboard);
        }

        $telegraph->send();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Telegram broadcast delivery failed', [
            'telegraph_chat_id' => $this->chatId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
