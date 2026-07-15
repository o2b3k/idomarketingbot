<?php

namespace App\Modules\Leads\Enums;

enum LeadCaptureStep: string
{
    case Started = 'started';
    case Name = 'name';
    case Phone = 'phone';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Started => 'Начал',
            self::Name => 'Указал имя',
            self::Phone => 'Указал телефон',
            self::Completed => 'Заполнил форму',
            self::Cancelled => 'Отменил',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Started => 'gray',
            self::Name => 'info',
            self::Phone => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
