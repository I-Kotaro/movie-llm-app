<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MovieLlmService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('services.groq.key', env('GROQ_API_KEY', ''));
    }

    /**
     * 映画についての質問をLLMに送信し、回答を取得します。
     *
     * @param string $prompt ユーザーからのプロンプトや質問
     * @param array $history 過去の会話履歴
     * @param string $model 使用するモデル
     * @param bool $isJson JSONモードで出力するかどうか
     * @return string LLMからの返答
     */
    public function ask(string $prompt, array $history = [], string $model = 'llama-3.3-70b-versatile', bool $isJson = false): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('GROQ_API_KEY is not set.');
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'あなたはユーザーの要望に沿った映画を提案する優秀な映画専門コンシェルジュです。映画に関する質問に対して、簡潔で魅力的な正しい日本語を使って敬語で答えてください。天気、ニュース、プログラミング、一般的な雑談など、映画検索や提案に直接関係のない質問には絶対に答えないでください。「私は映画専門のコンシェルジュですので、映画についてお尋ねください」と返答してください。',
            ]
        ];

        // 過去の履歴を追加
        $recentHistory = array_slice($history, -10);
        foreach ($recentHistory as $msg) {
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

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
        ];

        if ($isJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            \Log::error('Groq API Error (ask): ' . $response->body());
            throw new \Exception('LLM API request failed: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * ユーザーのプロンプトから検索モードとパラメータをJSONで抽出します。
     *
     * @param string $prompt
     * @param array $history
     * @param string $model
     * @return string JSON文字列
     */
    public function extractSearchQuery(string $prompt, array $history = [], string $model = 'llama-3.3-70b-versatile'): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('GROQ_API_KEY is not set.');
        }

        $currentDate = now()->format('Y');

        $systemInstruction = "あなたは最適な映画検索戦略を決定するアシスタントです。ユーザーの要望を分析し、以下のJSON形式のみを出力してください。\n"
            . "現在の年は {$currentDate} 年です。\n\n"
            . "【検索モードの判定基準】\n"
            . "- 'title'モード: 「古代ローマ人が風呂に入る」「ドンデン返しがある」「泣ける」「アクション映画」など、具体的なストーリー、設定、感情による検索の場合はこちらを選び、該当する映画のタイトルを推測してください。\n"
            . "- 'discover'モード: 「最新のアニメ」「2020年代のコメディ」など、公開年や明確なジャンルのみで絞り込める場合はこちらを選んでください。\n\n"
            . "【利用可能なジャンルID (discoverモード用)】\n"
            . "アクション:28, アドベンチャー:12, アニメ:16, コメディ:35, クライム:80, ドキュメンタリー:99, ドラマ:18, ファミリー:10751, ファンタジー:14, 歴史:36, ホラー:27, 音楽:10402, ミステリー:9648, ロマンス:10749, SF:878, スリラー:53, 戦争:10752, 西部劇:37\n\n"
            . "【出力JSONフォーマット】\n"
            . "{\n"
            . "  \"mode\": \"'title' または 'discover'\",\n"
            . "  \"titles\": [\"タイトル1\", \"タイトル2\", ...], // modeが'title'の場合のみ必須。合致する映画を10個以上リストアップ\n"
            . "  \"discover_params\": {\n"
            . "    \"with_genres\": \"抽出したジャンルID（複数ある場合はカンマ区切り）\",\n"
            . "    \"primary_release_year\": \"年（数値）またはnull\"\n"
            . "  }\n"
            . "}\n\n"
            . "※映画と無関係な場合は、空のJSON `{}` を出力してください。";

        $messages = [
            [
                'role' => 'system',
                'content' => $systemInstruction,
            ]
        ];

        $recentHistory = array_slice($history, -4);
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
            'content' => $prompt,
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            \Log::error('Groq API Error (extractSearchQuery): ' . $response->body());
            throw new \Exception('Groq API request failed: ' . $response->body());
        }

        return trim($response->json('choices.0.message.content', ''));
    }
}
