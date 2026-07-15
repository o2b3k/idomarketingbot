<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Modules\Leads\Enums\LeadCaptureStep;
use App\Modules\Leads\Enums\LeadStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Имя')
                    ->placeholder('Не указано')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->placeholder('Не указан')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('company')
                    ->label('Компания')
                    ->placeholder('Не указана')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (LeadStatus $state): string => $state->label())
                    ->color(fn (LeadStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('capture_step')
                    ->label('Этап')
                    ->badge()
                    ->formatStateUsing(fn (LeadCaptureStep $state): string => $state->label())
                    ->color(fn (LeadCaptureStep $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('tg_username')
                    ->label('Telegram')
                    ->formatStateUsing(fn (?string $state): string => $state ? "@$state" : '—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Источник')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Получен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(collect(LeadStatus::cases())->mapWithKeys(
                        fn (LeadStatus $status): array => [$status->value => $status->label()],
                    )->all()),
                SelectFilter::make('capture_step')
                    ->label('Этап заполнения')
                    ->options(collect(LeadCaptureStep::cases())->mapWithKeys(
                        fn (LeadCaptureStep $step): array => [$step->value => $step->label()],
                    )->all()),
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options(['telegram' => 'Telegram']),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
