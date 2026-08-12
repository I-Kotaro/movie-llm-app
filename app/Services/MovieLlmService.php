<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MovieLlmService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct()
    {
        $this->apiKey = config('services.groq.key');
    }

    /**
     * 映画についての質問をLLMに送信し、回答を取得します。
     *
     * @param string $prompt ユーザーからのプロンプトや質問
     * @param string $model 使用するモデル (デフォルト: llama-3.1-8b-instant)
     * @return string LLMからの返答
     */
    public function ask(string $prompt, string $model = 'llama-3.1-8b-instant'): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'あなたはユーザーの要望に沿った映画を提案する優秀なコンシェルジュです。映画に関する質問に対して、簡潔で魅力的な日本語を使って敬語で答えてください。',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new \Exception('LLM API request failed: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }
}
