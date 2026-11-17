<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Pixelforge\WebhookReceiver;
use App\Services\Pixelforge\WebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelforgeController extends Controller
{
    public function __invoke(
        Request $request,
        WebhookSignatureVerifier $verifier,
        WebhookReceiver $receiver,
    ): Response {
        $rawBody = $request->getContent();

        $verifier->verify(
            $rawBody,
            $request->header('Pixelforge-Signature'),
        );

        $receiver->receive(
            $rawBody,
            $request->header('Pixelforge-Event-Id'),
            $request->header('Pixelforge-Event-Type'),
        );

        return response()->noContent();
    }
}
