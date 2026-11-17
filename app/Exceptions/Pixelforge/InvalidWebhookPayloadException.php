<?php

namespace App\Exceptions\Pixelforge;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvalidWebhookPayloadException extends Exception
{
    public function render(Request $request): Response
    {
        return response($this->getMessage(), 400);
    }
}
