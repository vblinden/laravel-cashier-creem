<?php

namespace Laravel\Cashier\Creem\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyWebhookSignature
{
    public const SIGNATURE_HEADER = 'creem-signature';

    public function handle(Request $request, Closure $next)
    {
        $signature = $request->header(self::SIGNATURE_HEADER);
        $secret = config('cashier.webhook_secret');

        if (empty($secret) || empty($signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        return $next($request);
    }
}