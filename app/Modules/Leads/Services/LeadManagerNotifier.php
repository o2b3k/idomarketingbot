<?php

namespace App\Modules\Leads\Services;

use App\Modules\Leads\Models\Lead;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadManagerNotifier
{
    public function notify(TelegraphBot $bot, Lead $lead): void
    {
        $managerChatId = trim((string) config('services.telegram.manager_chat_id'));

        if ($managerChatId === '') {
            return;
        }

        $message = implode("\n", [
            '🆕 Новая заявка',
            '',
            'Имя: '.e($lead->name),
            'Телефон: '.e($lead->phone),
            'Компания: '.e($lead->company),
        ]);

        try {
            Telegraph::bot($bot)
                ->chat($managerChatId)
                ->message($message)
                ->send();
        } catch (Throwable $throwable) {
            Log::warning('Failed to notify Telegram lead manager', [
                'lead_id' => $lead->getKey(),
                'exception' => $throwable::class,
            ]);

            report($throwable);
        }
    }
}
