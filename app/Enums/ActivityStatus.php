<?php

namespace App\Enums;

enum ActivityStatus: string
{
    //
     case Pending = 'pending';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Done => 'Done',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Done => 'green',
        };
    }
}
