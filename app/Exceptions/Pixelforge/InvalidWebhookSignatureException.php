<?php

namespace App\Exceptions\Pixelforge;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvalidWebhookSignatureException extends Exception
{
    public function render(Request $request): Response
    {
        return response('Invalid Pixelforge signature.', 400);
    }
}
