<?php

use App\Filament\Pages\BroadcastMessage;
use App\Jobs\SendTelegramBroadcast;
use App\Models\User;
use App\Modules\Leads\Enums\LeadCaptureStep;
use App\Modules\Leads\Models\Lead;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Telegraph::fake();
});

test('manager can queue a text broadcast with multiple link buttons', function () {
    Queue::fake();

    $bot = TelegraphBot::query()->create(['token' => 'broadcast-bot', 'name' => 'Broadcast bot']);
    $recipient = $bot->chats()->create(['chat_id' => '101', 'name' => 'Lead']);
    $group = $bot->chats()->create(['chat_id' => '-100999', 'name' => 'Manager group']);
    Lead::factory()->create(['tg_chat_id' => 101, 'capture_step' => LeadCaptureStep::Completed]);

    Livewire::test(BroadcastMessage::class)
        ->fillForm([
            'type' => 'text',
            'text' => 'Новое предложение',
            'buttons' => [
                ['label' => 'Сайт', 'url' => 'https://example.com'],
                ['label' => 'Подробнее', 'url' => 'https://example.com/more'],
            ],
        ])
        ->call('send')
        ->assertHasNoFormErrors();

    Queue::assertPushed(SendTelegramBroadcast::class, fn (SendTelegramBroadcast $job): bool => $job->chatId === $recipient->getKey()
        && $job->type === 'text'
        && count($job->buttons) === 2);
    Queue::assertNotPushed(SendTelegramBroadcast::class, fn (SendTelegramBroadcast $job): bool => $job->chatId === $group->getKey());
});

test('broadcast job sends text with link buttons', function () {
    $bot = TelegraphBot::query()->create(['token' => 'broadcast-bot', 'name' => 'Broadcast bot']);
    $chat = $bot->chats()->create(['chat_id' => '202', 'name' => 'Lead']);

    (new SendTelegramBroadcast(
        $chat->getKey(),
        'text',
        'Привет!',
        null,
        [['label' => 'Открыть', 'url' => 'https://example.com']],
    ))->handle();

    Telegraph::assertSent('Привет!', exact: false);
});

test('broadcast job sends photo video and documents with captions', function () {
    Storage::fake('local');

    $bot = TelegraphBot::query()->create(['token' => 'media-bot', 'name' => 'Media bot']);
    $chat = $bot->chats()->create(['chat_id' => '303', 'name' => 'Lead']);
    $photo = UploadedFile::fake()->image('offer.jpg')->store('broadcasts', 'local');
    $video = UploadedFile::fake()->create('offer.mp4', 100, 'video/mp4')->store('broadcasts', 'local');
    $document = UploadedFile::fake()->create('offer.pdf', 100, 'application/pdf')->store('broadcasts', 'local');

    (new SendTelegramBroadcast($chat->getKey(), 'photo', 'Подпись фото', $photo))->handle();
    (new SendTelegramBroadcast($chat->getKey(), 'video', 'Подпись видео', $video))->handle();
    (new SendTelegramBroadcast($chat->getKey(), 'document', 'Подпись файла', $document))->handle();

    Telegraph::assertSentData('sendPhoto');
    Telegraph::assertSentData('sendVideo');
    Telegraph::assertSentData('sendDocument');
});
