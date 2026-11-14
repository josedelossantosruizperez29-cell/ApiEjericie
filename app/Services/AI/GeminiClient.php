<?php

namespace App\Services\AI;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GeminiClient
{
    private function endpoint(): string
    {
        $model = preg_replace('#^models/#', '', (string) config('gemini.model'));
        if (! config('gemini.key') || ! preg_match('/^[a-zA-Z0-9._-]+$/D', $model)) {
            throw new HttpException(503, 'configuration');
        }

        return 'https://generativelanguage.googleapis.com/v1beta/models/'.$model;
    }

    private function request()
    {
        return Http::withoutRedirecting()->withHeaders(['x-goog-api-key' => config('gemini.key')])->connectTimeout(5)->timeout(45);
    }

    public function verifyModel(): void
    {
        $url = $this->endpoint();
        try {
            $res = $this->request()->get($url);
        } catch (ConnectionException $e) {
            throw $this->connectionError($e);
        }
        $this->checkStatus($res);
        if (! in_array('generateContent', $res->json('supportedGenerationMethods', []), true)) {
            throw new HttpException(502, 'unsupported_model');
        }
    }

    private function checkStatus(Response $res): void
    {
        if ($res->successful()) {
            return;
        }
        $reason = match ($res->status()) {
            400 => in_array('API_KEY_INVALID', array_column((array) $res->json('error.details', []), 'reason'), true) ? 'authentication' : 'invalid_request',
            401 => 'authentication',403 => 'permission',404 => 'model_unavailable',429 => 'provider_quota',default => 'provider_error',
        };
        throw new HttpException(503, $reason);
    }

    private function connectionError(ConnectionException $e): HttpException
    {
        $error = $e->getPrevious();
        $code = $error instanceof ConnectException ? ($error->getHandlerContext()['errno'] ?? 0) : 0;

        return new HttpException(503, match ($code) {
            28 => 'timeout',6 => 'dns',default => 'connection'
        });
    }

    public function generate(string $instruction, array $context, array $schema, int $maxTokens = 8192, int $attempts = 2): array
    {
        $url = $this->endpoint().':generateContent';
        $payload = ['systemInstruction' => ['parts' => [['text' => $instruction]]], 'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($context, JSON_THROW_ON_ERROR)]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => $schema, 'maxOutputTokens' => $maxTokens]];
        for ($attempt = 1; $attempt <= min(2, $attempts); $attempt++) {
            try {
                $res = $this->request()->post($url, $payload);
            } catch (ConnectionException $e) {
                if ($attempt < min(2, $attempts)) {
                    continue;
                }
                throw $this->connectionError($e);
            }
            if ($res->serverError() && $attempt < min(2, $attempts)) {
                continue;
            }
            $this->checkStatus($res);
            if ($res->json('candidates.0.finishReason') !== 'STOP') {
                throw new HttpException(502, 'incomplete_response');
            }
            $parts = $res->json('candidates.0.content.parts');
            if (! is_array($parts)) {
                throw new HttpException(502, 'invalid_response');
            }
            $text = '';
            foreach ($parts as $part) {
                if (is_array($part) && empty($part['thought']) && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
            if ($text === '' || strlen($text) > 256000) {
                throw new HttpException(502, 'invalid_response');
            }
            $usage = array_filter(array_intersect_key((array) $res->json('usageMetadata'), array_flip(['promptTokenCount', 'candidatesTokenCount', 'totalTokenCount'])), 'is_int');

            return [$text, $usage];
        }
        throw new HttpException(503, 'provider_error');
    }
}
