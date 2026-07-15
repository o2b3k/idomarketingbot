<?php

namespace App\Modules\Leads\Services;

use App\Modules\Leads\Enums\LeadCaptureStep;
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
            $normalizedPhone = $number->formatE164();

            if ($number->isOfCountry('KG') && preg_match('/^\+996\d{9}$/', $normalizedPhone) === 1) {
                return $normalizedPhone;
            }
        } catch (Throwable) {
        }

        Log::debug('Lead phone normalization failed', ['country' => 'KG']);

        return null;
    }

    /**
     * @param  array{tg_user_id: int, tg_chat_id: int, tg_username?: string|null, meta?: array<string, mixed>}  $data
     */
    public function startFromBot(array $data): Lead
    {
        Log::info('Creating lead at Telegram dialog start', [
            'tg_user_id' => $data['tg_user_id'],
            'tg_chat_id' => $data['tg_chat_id'],
        ]);

        return Lead::query()->updateOrCreate(
            ['tg_user_id' => $data['tg_user_id']],
            [
                'tg_chat_id' => $data['tg_chat_id'],
                'tg_username' => $data['tg_username'] ?? null,
                'name' => null,
                'phone' => null,
                'phone_raw' => null,
                'company' => null,
                'source' => 'telegram',
                'status' => LeadStatus::New,
                'capture_step' => LeadCaptureStep::Started,
                'meta' => $data['meta'] ?? [],
            ],
        );
    }

    public function recordName(int $telegramUserId, string $name): Lead
    {
        return $this->updateCapture($telegramUserId, LeadCaptureStep::Name, ['name' => $name]);
    }

    public function hasCompletedLead(int $telegramUserId): bool
    {
        return Lead::query()
            ->where('tg_user_id', $telegramUserId)
            ->where('capture_step', LeadCaptureStep::Completed)
            ->exists();
    }

    public function recordPhone(int $telegramUserId, string $phoneRaw): Lead
    {
        $normalizedPhone = $this->normalizePhone($phoneRaw);

        if ($normalizedPhone === null) {
            throw new \InvalidArgumentException('The supplied phone number is invalid.');
        }

        return $this->updateCapture($telegramUserId, LeadCaptureStep::Phone, [
            'phone' => $normalizedPhone,
            'phone_raw' => $phoneRaw,
        ]);
    }

    public function markCancelled(int $telegramUserId): ?Lead
    {
        $lead = Lead::query()->where('tg_user_id', $telegramUserId)->first();

        if ($lead === null) {
            return null;
        }

        $lead->update(['capture_step' => LeadCaptureStep::Cancelled]);

        return $lead;
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
                'capture_step' => LeadCaptureStep::Completed,
                'meta' => $data['meta'] ?? [],
            ],
        );
    }

    /** @param array<string, mixed> $attributes */
    private function updateCapture(int $telegramUserId, LeadCaptureStep $step, array $attributes): Lead
    {
        $lead = Lead::query()->where('tg_user_id', $telegramUserId)->firstOrFail();
        $lead->update([...$attributes, 'capture_step' => $step]);

        Log::info('Telegram lead capture advanced', [
            'tg_user_id' => $telegramUserId,
            'capture_step' => $step->value,
        ]);

        return $lead;
    }
}
