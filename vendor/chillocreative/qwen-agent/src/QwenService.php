<?php

namespace Chillocreative\QwenAgent;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dev-tooling client for the Qwen coding sub-agent (php artisan qwen:agent).
 *
 * Config comes from .env (config/qwen.php) — a developer credential for
 * delegated implementation tasks, not an admin-configurable product feature.
 *
 * Never throws — always returns a normalised
 * ['ok'=>bool,'content'=>string|null,'raw'=>array|null,'error'=>string|null]
 * array so callers can degrade gracefully.
 */
class QwenService
{
    /**
     * Send a single-turn chat completion to Qwen (DashScope/QwenCloud
     * OpenAI-compatible API).
     */
    public function chat(string $systemPrompt, string $userPrompt, ?int $maxTokens = null, int $timeout = 120, ?string $model = null): array
    {
        $apiKey = config('qwen.api_key');

        if (empty($apiKey)) {
            return ['ok' => false, 'content' => null, 'raw' => null, 'error' => 'qwen_disabled'];
        }

        $baseUrl = rtrim(config('qwen.base_url'), '/');

        $payload = [
            'model' => $model ?: config('qwen.model'),
            'max_tokens' => $maxTokens ?? 4096,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/chat/completions', $payload);

            if (! $response->successful()) {
                $error = $response->json('error.message') ?? $response->body();
                Log::error('Qwen API call failed', ['status' => $response->status(), 'error' => $error]);

                return ['ok' => false, 'content' => null, 'raw' => $response->json(), 'error' => $error];
            }

            $text = $response->json('choices.0.message.content');

            if (! is_string($text) || $text === '') {
                return ['ok' => false, 'content' => null, 'raw' => $response->json(), 'error' => 'no_content'];
            }

            return ['ok' => true, 'content' => $text, 'raw' => $response->json(), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Qwen API exception: '.$e->getMessage());

            return ['ok' => false, 'content' => null, 'raw' => null, 'error' => $e->getMessage()];
        }
    }
}
