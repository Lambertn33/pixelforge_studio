<?php

namespace App\Enums;

enum PixelforgeEventType: string
{
    case GenerationProcessing = 'generation.processing';
    case GenerationSucceeded = 'generation.succeeded';
    case GenerationFailed = 'generation.failed';
    case Ping = 'ping';
}
