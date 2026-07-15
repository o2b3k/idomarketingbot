<?php

namespace App\Modules\Leads\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class VerifyTelegramWebhook
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredSecret = (string) config('telegraph.webhook.secret_token');
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            Log::warning('Rejected Telegram webhook with invalid secret');

            abort(Response::HTTP_FORBIDDEN);
        }

        $telegramUserId = $request->integer('message.from.id');

        if ($telegramUserId > 0) {
            $key = "telegram-webhook:$telegramUserId";
            $maxAttempts = (int) config('telegraph.webhook.throttle.max_attempts', 30);
            $decaySeconds = (int) config('telegraph.webhook.throttle.decay_seconds', 60);

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                Log::notice('Telegram webhook rate limit exceeded', [
                    'tg_user_id' => $telegramUserId,
                ]);

                abort(Response::HTTP_TOO_MANY_REQUESTS);
            }

            RateLimiter::hit($key, $decaySeconds);
        }

        return $next($request);
    }
}
