<?php

namespace App\Modules\Leads\Services;

use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Models\Lead;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LeadService
{
    /**
     * Normalize Kyrgyz phone numbers to E.164.
     */
    public function normalizePhone(string $phone): ?string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return null;
        }

        try {
            $number = phone($phone, 'KG');

            if ($number->isOfCountry('KG')) {
                return $number->formatE164();
            }
        } catch (Throwable) {
        }

        Log::debug('Lead phone normalization failed', [
            'digits' => preg_replace('/\D+/', '', $phone),
        ]);

        return null;
    }

    /**
     * @param  array{tg_user_id: int, tg_chat_id: int, tg_username?: string|null, name: string, phone_raw: string, company: string, meta?: array<string, mixed>}  $data
     */
    public function createFromBot(array $data): Lead
    {
        $normalizedPhone = $this->normalizePhone($data['phone_raw']);

        if ($normalizedPhone === null) {
            throw new \InvalidArgumentException('The supplied phone number is invalid.');
        }

        Log::info('Persisting Telegram lead', [
            'tg_user_id' => $data['tg_user_id'],
            'tg_chat_id' => $data['tg_chat_id'],
        ]);

        return Lead::query()->updateOrCreate(
            ['tg_user_id' => $data['tg_user_id']],
            [
                'tg_chat_id' => $data['tg_chat_id'],
                'tg_username' => $data['tg_username'] ?? null,
                'name' => $data['name'],
                'phone' => $normalizedPhone,
                'phone_raw' => $data['phone_raw'],
                'company' => $data['company'],
                'source' => 'telegram',
                'status' => LeadStatus::New,
                'meta' => $data['meta'] ?? [],
            ],
        );
    }
}
