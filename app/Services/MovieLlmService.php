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
     * @param array $history 過去の会話履歴 [['role' => '...', 'content' => '...'], ...]
     * @param string $model 使用するモデル (デフォルト: llama-3.1-8b-instant)
     * @return string LLMからの返答
     */
    public function ask(string $prompt, array $history = [], string $model = 'llama-3.1-8b-instant'): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'あなたはユーザーの要望に沿った映画を提案する優秀なコンシェルジュです。映画に関する質問に対して、簡潔で魅力的な日本語を使って敬語で答えてください。',
            ]
        ];

        // 過去の履歴を追加 (直近5往復程度までに制限してトークンを節約)
        $recentHistory = array_slice($history, -10);
        foreach ($recentHistory as $msg) {
            // 今回のプロンプトと同じ内容のuserメッセージ（末尾）は重複になるので除外
            if ($msg['role'] === 'user' && $msg['content'] === $prompt) {
                continue;
            }
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // 最新のプロンプトを追加
        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new \Exception('LLM API request failed: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * ユーザーのプロンプトから要望に合う映画のタイトルを抽出（推測）します。
     *
     * @param string $prompt
     * @param array $history
     * @param string $model
     * @return string
     */
    public function extractSearchQuery(string $prompt, array $history = [], string $model = 'llama-3.1-8b-instant'): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'あなたは映画データベース検索AIです。ユーザーの要望に最も合致する「実在する有名な映画のタイトル」を最大3つ、カンマ区切りで出力してください。会話文や余計な記号は一切含めないでください。例：「トイ・ストーリー, レゴ・ムービー, シュガー・ラッシュ」',
            ]
        ];

        // 過去の履歴を少しだけ追加して文脈を理解させる
        $recentHistory = array_slice($history, -6);
        foreach ($recentHistory as $msg) {
            if ($msg['role'] === 'user' && $msg['content'] === $prompt) {
                continue;
            }
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt . "\n\n【重要指令】\n1. 上記までの会話の文脈を踏まえて、ユーザーが知りたい映画の「タイトル」または「俳優名・監督名」のみをカンマ区切りで出力してください。あらすじや説明、会話文は絶対に書かないでください。\n2. もしユーザーの発言が「映画を探すこと」に全く関係ない場合（例：天気を聞く、日常の挨拶など）は、無理に映画を推測せず、必ず「NO_MOVIE_QUERY」という文字列のみを出力してください。\n3. 人物名が入力された場合は、勝手に映画タイトルに変換せず、そのまま人物名を出力してください。",
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
            ]);

        if ($response->failed()) {
            return '';
        }

        return trim($response->json('choices.0.message.content', ''));
    }
}
