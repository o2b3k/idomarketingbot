<?php

namespace App\Modules\Leads\Webhooks;

use App\Modules\Leads\Services\LeadManagerNotifier;
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

    public function __construct(
        private readonly LeadService $leadService,
        private readonly LeadManagerNotifier $leadManagerNotifier,
    ) {
        parent::__construct();
    }

    public function start(): void
    {
        $this->forgetState();
        $this->putState(['step' => 'name']);
        $this->leadService->startFromBot($this->telegramIdentity());

        Log::info('Telegram lead dialog started', ['tg_user_id' => $this->telegramUserId()]);
        $this->reply('Здравствуйте! Как к вам обращаться? Напишите имя');
    }

    public function cancel(): void
    {
        $this->forgetState();
        $this->leadService->markCancelled($this->telegramUserId());

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
        $this->leadService->recordName($this->telegramUserId(), $name);

        $keyboard = ReplyKeyboard::make()
            ->row([ReplyButton::make('Поделиться номером')->requestContact()])
            ->resize()
            ->oneTime();

        $this->chat->message("Приятно, {$name}. Оставьте номер телефона — кнопкой ниже или введите вручную.")
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
            $this->reply('Не удалось распознать номер. Введите номер Кыргызстана, например +996 555 123 456 или 0555 123 456.');

            return;
        }

        $state['phone_raw'] = $phoneRaw;
        $state['step'] = 'company';
        $this->putState($state);
        $this->leadService->recordPhone($telegramUserId, $phoneRaw);

        $this->chat->message('Спасибо. Как называется ваша компания?')
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

        $lead = $this->leadService->createFromBot([
            'tg_user_id' => $this->telegramUserId(),
            'tg_chat_id' => (int) $this->chat->chat_id,
            'tg_username' => $from?->username() ?: null,
            'name' => (string) $state['name'],
            'phone_raw' => (string) $state['phone_raw'],
            'company' => $company,
            'meta' => ['language_code' => $from?->languageCode() ?: null],
        ]);

        $this->leadManagerNotifier->notify($this->bot, $lead);

        $this->forgetState();

        Log::info('Telegram lead dialog completed', ['tg_user_id' => $this->telegramUserId()]);
        $this->chat->message('Готово! Мы записали заявку и скоро свяжемся. 🙌')
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

    /**
     * @return array{tg_user_id: int, tg_chat_id: int, tg_username: string|null, meta: array<string, string|null>}
     */
    private function telegramIdentity(): array
    {
        $from = $this->message?->from();

        return [
            'tg_user_id' => $this->telegramUserId(),
            'tg_chat_id' => (int) $this->chat->chat_id,
            'tg_username' => $from?->username() ?: null,
            'meta' => ['language_code' => $from?->languageCode() ?: null],
        ];
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('telegraph.storage.stores.cache.store'));
    }
}
