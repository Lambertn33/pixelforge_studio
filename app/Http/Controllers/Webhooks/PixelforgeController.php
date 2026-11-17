<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Pixelforge\WebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelforgeController extends Controller
{
    public function __invoke(Request $request, WebhookSignatureVerifier $verifier): Response
    {
        $verifier->verify(
            $request->getContent(),
            $request->header('Pixelforge-Signature'),
        );

        return response()->noContent();
    }
}
