<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed => true,
            self::Queued, self::Processing => false,
        };
    }
}
