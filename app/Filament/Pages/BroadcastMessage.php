<?php

namespace App\Filament\Pages;

use App\Jobs\SendTelegramBroadcast;
use App\Modules\Leads\Enums\LeadCaptureStep;
use App\Modules\Leads\Models\Lead;
use BackedEnum;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BroadcastMessage extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use RestrictsFileUploadsToSchemaComponents;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Рассылка';

    protected static ?string $title = 'Telegram-рассылка';

    protected string $view = 'filament.pages.broadcast-message';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['type' => 'text', 'buttons' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('Тип сообщения')->options([
                'text' => 'Текст', 'photo' => 'Фото', 'video' => 'Видео', 'document' => 'Файл (PDF, Word, Excel)',
            ])->required()->live(),
            Textarea::make('text')->label('Текст / подпись')->rows(6)
                ->required(fn ($get): bool => $get('type') === 'text')
                ->maxLength(fn ($get): int => $get('type') === 'text' ? 4096 : 1024),
            FileUpload::make('media')->label('Фото, видео или файл')->disk('local')->directory('broadcasts')
                ->visible(fn ($get): bool => $get('type') !== 'text')
                ->required(fn ($get): bool => $get('type') !== 'text')
                ->maxSize(51200)
                ->acceptedFileTypes(fn ($get): array => match ($get('type')) {
                    'photo' => ['image/jpeg', 'image/png', 'image/webp'],
                    'video' => ['video/mp4', 'video/quicktime'],
                    'document' => [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                    ],
                    default => [],
                }),
            Repeater::make('buttons')->label('Кнопки-ссылки')->schema([
                TextInput::make('label')->label('Текст кнопки')->required()->maxLength(64),
                TextInput::make('url')->label('Ссылка')->url()->required()->maxLength(2048),
            ])->columns(2)->addActionLabel('Добавить кнопку')->maxItems(10),
        ])->statePath('data');
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $chatIds = Lead::query()->where('capture_step', LeadCaptureStep::Completed)
            ->whereNotNull('tg_chat_id')->distinct()->pluck('tg_chat_id');
        $botId = TelegraphBot::query()->value('id');
        $chats = TelegraphChat::query()
            ->where('telegraph_bot_id', $botId)
            ->whereIn('chat_id', $chatIds)
            ->get();

        foreach ($chats as $chat) {
            SendTelegramBroadcast::dispatch(
                $chat->getKey(), $data['type'], $data['text'] ?? null,
                $data['media'] ?? null, array_values($data['buttons'] ?? []),
            );
        }

        Notification::make()->success()->title("Рассылка поставлена в очередь: {$chats->count()} получателей")->send();
        $this->form->fill(['type' => 'text', 'buttons' => []]);
    }
}
