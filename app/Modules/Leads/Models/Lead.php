<?php

namespace App\Modules\Leads\Models;

use App\Modules\Leads\Enums\LeadCaptureStep;
use App\Modules\Leads\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tg_user_id
 * @property int $tg_chat_id
 * @property string|null $tg_username
 * @property string|null $name
 * @property string|null $phone
 * @property string|null $phone_raw
 * @property string|null $company
 * @property string $source
 * @property LeadStatus $status
 * @property LeadCaptureStep $capture_step
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tg_user_id', 'tg_chat_id', 'tg_username', 'name', 'phone', 'phone_raw', 'company', 'source', 'status', 'capture_step', 'meta'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected static function newFactory(): LeadFactory
    {
        return LeadFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tg_user_id' => 'integer',
            'tg_chat_id' => 'integer',
            'status' => LeadStatus::class,
            'capture_step' => LeadCaptureStep::class,
            'meta' => 'array',
        ];
    }
}
