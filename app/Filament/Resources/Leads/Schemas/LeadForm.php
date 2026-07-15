<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Modules\Leads\Enums\LeadStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Контактные данные')
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->maxLength(100),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('company')
                            ->label('Компания')
                            ->maxLength(150),
                        Select::make('status')
                            ->label('Статус')
                            ->options(collect(LeadStatus::cases())->mapWithKeys(
                                fn (LeadStatus $status): array => [$status->value => $status->label()],
                            )->all())
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Telegram')
                    ->schema([
                        TextInput::make('tg_username')
                            ->label('Username')
                            ->disabled(),
                        TextInput::make('tg_user_id')
                            ->label('User ID')
                            ->disabled(),
                        TextInput::make('tg_chat_id')
                            ->label('Chat ID')
                            ->disabled(),
                        TextInput::make('source')
                            ->label('Источник')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }
}
