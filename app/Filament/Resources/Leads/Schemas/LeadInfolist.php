<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Modules\Leads\Enums\LeadStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Контактные данные')
                    ->schema([
                        TextEntry::make('name')->label('Имя'),
                        TextEntry::make('phone')->label('Телефон')->copyable(),
                        TextEntry::make('company')->label('Компания'),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn (LeadStatus $state): string => $state->label())
                            ->color(fn (LeadStatus $state): string => $state->color()),
                    ])
                    ->columns(2),
                Section::make('Telegram')
                    ->schema([
                        TextEntry::make('tg_username')
                            ->label('Username')
                            ->formatStateUsing(fn (?string $state): string => $state ? "@$state" : '—'),
                        TextEntry::make('tg_user_id')->label('User ID'),
                        TextEntry::make('tg_chat_id')->label('Chat ID'),
                        TextEntry::make('created_at')
                            ->label('Получен')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}
