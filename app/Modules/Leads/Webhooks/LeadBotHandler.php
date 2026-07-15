<?php

namespace App\Modules\Leads\Webhooks;

use App\Modules\Leads\Services\LeadService;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;
use Throwable;

final class LeadBotHandler extends WebhookHandler
{
    private const STATE_TTL_SECONDS = 1800;

    public function __construct(private readonly LeadService $leadService)
    {
        parent::__construct();
    }

    public function start(): void
    {
        $this->forgetState();
        $this->putState(['step' => 'name']);

        Log::info('Telegram lead dialog started', ['tg_user_id' => $this->telegramUserId()]);
        $this->reply('Здравствуйте! Как вас зовут?');
    }

    public function cancel(): void
    {
        $this->forgetState();

        Log::info('Telegram lead dialog cancelled', ['tg_user_id' => $this->telegramUserId()]);
        $this->chat->message('Диалог отменён. Отправьте /start, чтобы начать заново.')
            ->removeReplyKeyboard()
            ->send();
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $state = $this->state();

        if ($state === []) {
            $this->reply('Отправьте /start, чтобы оставить заявку.');

            return;
        }

        Log::debug('Processing Telegram lead dialog step', [
            'tg_user_id' => $this->telegramUserId(),
            'step' => $state['step'] ?? null,
        ]);

        match ($state['step'] ?? null) {
            'name' => $this->acceptName((string) $text, $state),
            'phone' => $this->acceptPhone((string) $text, $state),
            'company' => $this->acceptCompany((string) $text, $state),
            default => $this->start(),
        };
    }

    protected function onFailure(Throwable $throwable): void
    {
        Log::error('Telegram lead webhook failed', [
            'tg_user_id' => $this->message?->from()?->id(),
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
        ]);

        $this->forgetState();
        report($throwable);
        rescue(fn () => $this->chat->message('Произошла ошибка. Отправьте /start и попробуйте снова.')
            ->removeReplyKeyboard()
            ->send(), report: false);
    }

    /** @param array<string, mixed> $state */
    private function acceptName(string $name, array $state): void
    {
        $name = trim($name);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $this->reply('Введите имя длиной от 2 до 100 символов.');

            return;
        }

        $state['name'] = $name;
        $state['step'] = 'phone';
        $this->putState($state);

        $keyboard = ReplyKeyboard::make()
            ->row([ReplyButton::make('Поделиться номером')->requestContact()])
            ->resize()
            ->oneTime();

        $this->chat->message('Отправьте номер телефона кнопкой ниже или введите его вручную.')
            ->replyKeyboard($keyboard)
            ->send();
    }

    /** @param array<string, mixed> $state */
    private function acceptPhone(string $text, array $state): void
    {
        $contact = $this->message?->contact();
        $telegramUserId = $this->telegramUserId();

        if ($contact !== null && $contact->userId() !== null && $contact->userId() !== $telegramUserId) {
            $this->reply('Пожалуйста, отправьте именно свой контакт.');

            return;
        }

        $phoneRaw = $contact?->phoneNumber() ?? trim($text);

        if ($this->leadService->normalizePhone($phoneRaw) === null) {
            $this->reply('Не удалось распознать номер. Введите номер Узбекистана или Кыргызстана.');

            return;
        }

        $state['phone_raw'] = $phoneRaw;
        $state['step'] = 'company';
        $this->putState($state);

        $this->chat->message('Как называется ваша компания?')
            ->removeReplyKeyboard()
            ->send();
    }

    /** @param array<string, mixed> $state */
    private function acceptCompany(string $company, array $state): void
    {
        $company = trim($company);

        if (mb_strlen($company) < 2 || mb_strlen($company) > 150) {
            $this->reply('Введите название компании длиной от 2 до 150 символов.');

            return;
        }

        $from = $this->message?->from();

        $this->leadService->createFromBot([
            'tg_user_id' => $this->telegramUserId(),
            'tg_chat_id' => (int) $this->chat->chat_id,
            'tg_username' => $from?->username() ?: null,
            'name' => (string) $state['name'],
            'phone_raw' => (string) $state['phone_raw'],
            'company' => $company,
            'meta' => ['language_code' => $from?->languageCode() ?: null],
        ]);

        $this->forgetState();

        Log::info('Telegram lead dialog completed', ['tg_user_id' => $this->telegramUserId()]);
        $this->chat->message('Спасибо! Ваша заявка принята.')
            ->removeReplyKeyboard()
            ->send();
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        $state = $this->cache()->get($this->stateKey(), []);

        return is_array($state) ? $state : [];
    }

    /** @param array<string, mixed> $state */
    private function putState(array $state): void
    {
        $this->cache()->put($this->stateKey(), $state, self::STATE_TTL_SECONDS);
    }

    private function forgetState(): void
    {
        if ($this->message?->from() !== null) {
            $this->cache()->forget($this->stateKey());
        }
    }

    private function stateKey(): string
    {
        return 'lead-dialog:'.$this->telegramUserId();
    }

    private function telegramUserId(): int
    {
        return $this->message?->from()?->id() ?? 0;
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('telegraph.storage.stores.cache.store'));
    }
}
