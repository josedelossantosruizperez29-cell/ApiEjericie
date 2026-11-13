<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GeminiClient
{
    public function generate(string $instruction, array $context, array $schema): array
    {
        $model = config('gemini.model');
        $key = config('gemini.key');
        if (! $key || ! $model || ! preg_match('/^[a-zA-Z0-9._-]+$/D', $model)) {
            throw new HttpException(503, 'configuration');
        }
        $payload = ['systemInstruction' => ['parts' => [['text' => $instruction]]], 'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($context, JSON_THROW_ON_ERROR)]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => $schema, 'maxOutputTokens' => 8192]];
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $key])->connectTimeout(4)->timeout(25)->post("https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent", $payload);
            } catch (ConnectionException) {
                if ($attempt === 0) {
                    continue;
                }throw new HttpException(503, 'timeout');
            }
            if ($response->serverError() && $attempt === 0) {
                continue;
            }
            if (! $response->successful()) {
                throw new HttpException(503, $response->status() === 429 ? 'provider_quota' : 'provider_error');
            }
            $candidate = $response->json('candidates.0');
            if (($candidate['finishReason'] ?? null) !== 'STOP') {
                throw new HttpException(502, 'incomplete_response');
            }
            $parts = array_filter($candidate['content']['parts'] ?? [], fn ($p) => empty($p['thought']));
            $text = implode('', array_column($parts, 'text'));
            if ($text === '' || strlen($text) > 256000) {
                throw new HttpException(502, 'invalid_response');
            }

            return [$text, array_intersect_key($response->json('usageMetadata', []), array_flip(['promptTokenCount', 'candidatesTokenCount', 'totalTokenCount']))];
        }
        throw new HttpException(503, 'provider_error');
    }
}
