<?php

use App\Modules\Leads\Enums\LeadCaptureStep;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\LeadManagerNotifier;
use App\Modules\Leads\Services\LeadService;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('telegraph.storage.stores.cache.store', 'array');
    config()->set('telegraph.webhook.secret_token', 'test-webhook-secret');
    config()->set('telegraph.webhook.throttle.max_attempts', 30);
    config()->set('telegraph.webhook.throttle.decay_seconds', 60);
    config()->set('services.telegram.manager_chat_id');

    Telegraph::fake();

    TelegraphBot::query()->create([
        'token' => 'test-bot-token',
        'name' => 'Lead test bot',
    ]);
});

function sendLeadBotUpdate(
    int $updateId,
    int $telegramUserId,
    ?string $text = null,
    ?array $contact = null,
    bool $withSecret = true,
    string $chatType = 'private',
    ?int $chatId = null,
): TestResponse {
    $message = [
        'message_id' => $updateId,
        'date' => now()->timestamp,
        'from' => [
            'id' => $telegramUserId,
            'is_bot' => false,
            'first_name' => 'Test',
            'username' => 'lead_user',
            'language_code' => 'ru',
        ],
        'chat' => [
            'id' => $chatId ?? $telegramUserId,
            'type' => $chatType,
            'first_name' => 'Test',
        ],
    ];

    if ($text !== null) {
        $message['text'] = $text;
    }

    if ($contact !== null) {
        $message['contact'] = $contact;
    }

    $headers = $withSecret
        ? ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret']
        : [];

    return test()->postJson('/telegraph/test-bot-token/webhook', [
        'update_id' => $updateId,
        'message' => $message,
    ], $headers);
}

test('LeadBotFlow ignores regular messages and commands in groups', function () {
    sendLeadBotUpdate(1, 101, 'круто', chatType: 'supergroup', chatId: -1001234567890)
        ->assertNoContent();
    sendLeadBotUpdate(2, 101, '/start', chatType: 'supergroup', chatId: -1001234567890)
        ->assertNoContent();

    Telegraph::assertNothingSent();
    expect(Lead::query()->count())->toBe(0);
});

test('LeadBotFlow normalizes only Kyrgyz phone numbers', function () {
    $service = app(LeadService::class);
    $factoryLead = Lead::factory()->create();

    expect($service->normalizePhone('0555 123 456'))->toBe('+996555123456')
        ->and($service->normalizePhone('+996 555 123 456'))->toBe('+996555123456')
        ->and($service->normalizePhone('0778440455'))->toBe('+996778440455')
        ->and($service->normalizePhone('+996778440455'))->toBe('+996778440455')
        ->and($service->normalizePhone('+99677844045'))->toBeNull()
        ->and($service->normalizePhone('077844045'))->toBeNull()
        ->and($service->normalizePhone('+1 202 555 0123'))->toBeNull()
        ->and($service->normalizePhone('not-a-phone'))->toBeNull()
        ->and($service->hasCompletedLead($factoryLead->tg_user_id))->toBeTrue()
        ->and($factoryLead->exists)->toBeTrue();
});

test('LeadBotFlow captures a full conversation with a manually entered phone', function () {
    config()->set('services.telegram.manager_chat_id', '987654321');

    sendLeadBotUpdate(1, 101, '/start')->assertNoContent();
    Telegraph::assertSent('Здравствуйте! Как к вам обращаться? Напишите имя');

    $startedLead = Lead::query()->sole();
    expect($startedLead->capture_step)->toBe(LeadCaptureStep::Started)
        ->and($startedLead->name)->toBeNull()
        ->and($startedLead->phone)->toBeNull()
        ->and($startedLead->company)->toBeNull();

    sendLeadBotUpdate(2, 101, 'Алия')->assertNoContent();
    Telegraph::assertSentData('sendMessage', [
        'chat_id' => '101',
        'text' => 'Приятно, Алия. Оставьте номер телефона — кнопкой ниже или введите вручную.',
        'reply_markup' => [
            'keyboard' => [[['text' => 'Поделиться номером', 'request_contact' => true]]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ],
    ]);

    expect($startedLead->refresh()->capture_step)->toBe(LeadCaptureStep::Name)
        ->and($startedLead->name)->toBe('Алия')
        ->and($startedLead->phone)->toBeNull();

    sendLeadBotUpdate(3, 101, '0555 123 456')->assertNoContent();
    Telegraph::assertSent('Спасибо. Как называется ваша компания?');

    expect($startedLead->refresh()->capture_step)->toBe(LeadCaptureStep::Phone)
        ->and($startedLead->phone)->toBe('+996555123456')
        ->and($startedLead->company)->toBeNull();

    sendLeadBotUpdate(4, 101, 'Acme')->assertNoContent();
    Telegraph::assertSent('Готово! Мы записали заявку и скоро свяжемся. 🙌');
    Telegraph::assertSentData('sendMessage', [
        'chat_id' => '987654321',
        'text' => "🆕 Новая заявка\n\nИмя: Алия\nТелефон: +996555123456\nКомпания: Acme",
    ]);

    $lead = Lead::query()->sole();

    expect($lead->tg_user_id)->toBe(101)
        ->and($lead->name)->toBe('Алия')
        ->and($lead->phone)->toBe('+996555123456')
        ->and($lead->company)->toBe('Acme')
        ->and($lead->capture_step)->toBe(LeadCaptureStep::Completed);
});

test('LeadManagerNotifier sends escaped lead details to the configured manager', function () {
    config()->set('services.telegram.manager_chat_id', '-1001234567890');

    $lead = Lead::factory()->create([
        'name' => '<Алия>',
        'phone' => '+996555123456',
        'company' => 'A & B',
    ]);

    app(LeadManagerNotifier::class)->notify(TelegraphBot::query()->sole(), $lead);

    Telegraph::assertSentData('sendMessage', [
        'chat_id' => '-1001234567890',
        'text' => "🆕 Новая заявка\n\nИмя: &lt;Алия&gt;\nТелефон: +996555123456\nКомпания: A &amp; B",
    ]);
});

test('LeadBotFlow accepts a shared contact belonging to the sender', function () {
    sendLeadBotUpdate(10, 202, '/start')->assertNoContent();
    sendLeadBotUpdate(11, 202, 'Бек')->assertNoContent();
    sendLeadBotUpdate(12, 202, contact: [
        'phone_number' => '+996555123456',
        'first_name' => 'Бек',
        'user_id' => 202,
    ])->assertNoContent();
    sendLeadBotUpdate(13, 202, 'Nomad')->assertNoContent();

    expect(Lead::query()->sole()->phone)->toBe('+996555123456');
});

test('LeadBotFlow rejects invalid and foreign contact input without advancing', function () {
    sendLeadBotUpdate(20, 303, '/start')->assertNoContent();
    sendLeadBotUpdate(21, 303, 'A')->assertNoContent();
    Telegraph::assertSent('Введите имя длиной от 2 до 100 символов.');

    sendLeadBotUpdate(22, 303, 'Азиз')->assertNoContent();
    sendLeadBotUpdate(23, 303, 'junk')->assertNoContent();
    Telegraph::assertSent('Не удалось распознать номер. Введите номер Кыргызстана, например +996 555 123 456 или 0555 123 456.');

    sendLeadBotUpdate(24, 303, contact: [
        'phone_number' => '+996555123456',
        'first_name' => 'Other',
        'user_id' => 999,
    ])->assertNoContent();
    Telegraph::assertSent('Пожалуйста, отправьте именно свой контакт.');

    $lead = Lead::query()->sole();

    expect($lead->capture_step)->toBe(LeadCaptureStep::Name)
        ->and($lead->name)->toBe('Азиз')
        ->and($lead->phone)->toBeNull();
});

test('LeadBotFlow cancel resets the dialog', function () {
    sendLeadBotUpdate(30, 404, '/start')->assertNoContent();
    sendLeadBotUpdate(31, 404, 'Дана')->assertNoContent();
    sendLeadBotUpdate(32, 404, '/cancel')->assertNoContent();
    sendLeadBotUpdate(33, 404, '+996555123456')->assertNoContent();

    Telegraph::assertSent('Отправьте /start, чтобы оставить заявку.');
    expect(Lead::query()->sole()->capture_step)->toBe(LeadCaptureStep::Cancelled);
});

test('LeadBotFlow deduplicates repeated submissions by Telegram user id', function () {
    foreach ([['First', 'Alpha'], ['Second', 'Beta']] as $iteration => [$name, $company]) {
        $messageId = 40 + ($iteration * 5);
        sendLeadBotUpdate($messageId, 505, '/start')->assertNoContent();

        if ($iteration > 0) {
            Telegraph::assertSent('Вы уже оставляли заявку. Хотите заполнить её заново?');
            sendLeadBotUpdate($messageId + 1, 505, 'Да, заполнить заново')->assertNoContent();
        }

        sendLeadBotUpdate($messageId + 2, 505, $name)->assertNoContent();
        sendLeadBotUpdate($messageId + 3, 505, '+996555123456')->assertNoContent();
        sendLeadBotUpdate($messageId + 4, 505, $company)->assertNoContent();
    }

    expect(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->sole()->name)->toBe('Second')
        ->and(Lead::query()->sole()->company)->toBe('Beta');
});

test('LeadBotFlow preserves a completed lead when restart is declined', function () {
    sendLeadBotUpdate(80, 808, '/start')->assertNoContent();
    sendLeadBotUpdate(81, 808, 'Айбек')->assertNoContent();
    sendLeadBotUpdate(82, 808, '0555 123 456')->assertNoContent();
    sendLeadBotUpdate(83, 808, 'Nomad')->assertNoContent();

    sendLeadBotUpdate(84, 808, '/start')->assertNoContent();
    Telegraph::assertSentData('sendMessage', [
        'chat_id' => '808',
        'text' => 'Вы уже оставляли заявку. Хотите заполнить её заново?',
        'reply_markup' => [
            'keyboard' => [[
                ['text' => 'Да, заполнить заново'],
                ['text' => 'Нет'],
            ]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ],
    ]);

    sendLeadBotUpdate(85, 808, 'Нет')->assertNoContent();
    Telegraph::assertSent('Хорошо, текущая заявка сохранена.');

    $lead = Lead::query()->sole();

    expect($lead->name)->toBe('Айбек')
        ->and($lead->phone)->toBe('+996555123456')
        ->and($lead->company)->toBe('Nomad')
        ->and($lead->capture_step)->toBe(LeadCaptureStep::Completed);
});

test('LeadBotFlow rejects a webhook without its secret token', function () {
    sendLeadBotUpdate(60, 606, '/start', withSecret: false)->assertForbidden();

    expect(Lead::query()->count())->toBe(0);
    Telegraph::assertNothingSent();
});

test('LeadBotFlow throttles excessive updates per Telegram user', function () {
    config()->set('telegraph.webhook.throttle.max_attempts', 1);

    sendLeadBotUpdate(70, 707, '/start')->assertNoContent();
    sendLeadBotUpdate(71, 707, 'Name')->assertTooManyRequests();
});
