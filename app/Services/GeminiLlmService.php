<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiLlmService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
    }

    /**
     * 映画についての質問をLLMに送信し、回答を取得します。
     */
    public function ask(string $prompt, array $history = [], string $model = 'gemini-3.5-flash', bool $isJson = false): string
    {
        if (empty($this->apiKey)) {
            \Log::warning('GEMINI_API_KEY is not set. Returning fallback message.');
            return $isJson ? json_encode([
                "general_reply" => "APIキーが設定されていません。.envにGEMINI_API_KEYを設定してください。",
                "recommendations" => []
            ]) : "APIキーが設定されていません。.envにGEMINI_API_KEYを設定してください。";
        }

        $contents = $this->buildContents($prompt, $history);
        
        $systemInstruction = "あなたはユーザーの要望に沿った映画を提案する優秀なコンシェルジュです。映画に関する質問に対して、簡潔で魅力的な正しい日本語を使って敬語で答えてください。";

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]]
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.5,
            ]
        ];

        if ($isJson) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/{$model}:generateContent?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            \Log::error('Gemini API Error (ask): ' . $response->body());
            throw new \Exception('Gemini API request failed');
        }

        return trim($response->json('candidates.0.content.parts.0.text', ''));
    }

    /**
     * ユーザーのプロンプトから検索モードとパラメータをJSONで抽出します。
     */
    public function extractSearchQuery(string $prompt, array $history = [], string $model = 'gemini-3.5-flash'): string
    {
        if (empty($this->apiKey)) {
            \Log::warning('GEMINI_API_KEY is not set. Returning empty JSON.');
            return '{}';
        }

        // 過去の履歴を少しだけ含める（直近2件）
        $recentHistory = array_slice($history, -2);
        
        $currentDate = now()->format('Y');
        
        $instruction = "あなたは最適な映画検索戦略を決定するアシスタントです。ユーザーの要望を分析し、以下のJSON形式のみを出力してください。\n"
            . "現在の年は {$currentDate} 年です。\n\n"
            . "【検索モードの判定基準】\n"
            . "- 'title'モード: 「古代ローマ人が風呂に入る」「ドンデン返しがある」「泣ける」など、具体的なストーリー、設定、感情による検索の場合はこちらを選び、あなたが自力で該当する映画のタイトルを推測してください。\n"
            . "- 'discover'モード: 「最新のアニメ」「2020年代のコメディ」など、公開年や明確なジャンルのみで絞り込める場合はこちらを選んでください。\n\n"
            . "【利用可能なジャンルID (discoverモード用)】\n"
            . "アクション:28, アドベンチャー:12, アニメ:16, コメディ:35, クライム:80, ドキュメンタリー:99, ドラマ:18, ファミリー:10751, ファンタジー:14, 歴史:36, ホラー:27, 音楽:10402, ミステリー:9648, ロマンス:10749, SF:878, スリラー:53, 戦争:10752, 西部劇:37\n\n"
            . "【出力JSONフォーマット】\n"
            . "{\n"
            . "  \"mode\": \"'title' または 'discover'\",\n"
            . "  \"titles\": [\"タイトル1\", \"タイトル2\", ...], // modeが'title'の場合のみ必須。ユーザーの要望に合致する実在の映画を抽出してください。洋画だけでなく邦画も含めて広い視野で検討してください。ランダム性を高めるため、合致する映画を【必ず10個以上】リストアップしてください。\n"
            . "  \"discover_params\": {\n"
            . "    \"with_genres\": \"抽出したジャンルID（複数ある場合はカンマ区切り）\",\n"
            . "    \"primary_release_year\": \"ユーザーが『最新』等年代を指定した場合のみその年を数値で指定（例: {$currentDate}）。指定がなければnull\"\n"
            . "  } // modeが'discover'の場合のみ必須\n"
            . "}\n\n"
            . "※映画検索と無関係な場合は、空のJSON `{}` を出力してください。";

        $contents = $this->buildContents($prompt, $recentHistory);

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $instruction]]
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.6,
                'responseMimeType' => 'application/json',
            ]
        ];

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/{$model}:generateContent?key={$this->apiKey}", $payload);

        if ($response->failed()) {
            \Log::error('Gemini API Error (extractSearchQuery): ' . $response->body());
            throw new \Exception('Gemini API request failed');
        }

        return trim($response->json('candidates.0.content.parts.0.text', ''));
    }

    /**
     * フロントエンドからの会話履歴をGemini APIのcontents形式に変換する
     */
    private function buildContents(string $prompt, array $history): array
    {
        $contents = [];
        foreach ($history as $msg) {
            // roleをGemini形式 (user, model) に変換
            $role = $msg['role'] === 'user' ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]]
            ];
        }
        
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];

        return $contents;
    }
}
