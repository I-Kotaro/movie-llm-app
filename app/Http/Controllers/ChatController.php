<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use App\Services\GeminiLlmService;
use App\Services\MovieLlmService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected $tmdbService;
    protected $llmService;
    protected $fallbackLlmService;

    public function __construct(TmdbService $tmdbService, GeminiLlmService $llmService, MovieLlmService $fallbackLlmService)
    {
        $this->tmdbService = $tmdbService;
        $this->llmService = $llmService;
        $this->fallbackLlmService = $fallbackLlmService;
    }

    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
        ]);

        $userMessage = $request->input('message');
        $history = $request->input('history', []);
        \Log::info('ユーザーからのリクエストを受け付けました: ' . $userMessage);

        try {
            // 1. ユーザーのプロンプトから検索戦略（モード）とパラメータを抽出する
            $searchStrategyJson = $this->executeWithCascadingFallback(
                function ($model) use ($userMessage, $history) {
                    return $this->llmService->extractSearchQuery($userMessage, $history, $model);
                },
                function () use ($userMessage, $history) {
                    return $this->fallbackLlmService->extractSearchQuery($userMessage, $history, 'llama-3.3-70b-versatile');
                }
            );
            \Log::info('LLM Search Strategy: ' . $searchStrategyJson);
            
            // JSONを配列にデコード
            $strategy = json_decode($searchStrategyJson, true);
            
            if (empty($strategy) || empty($strategy['mode'])) {
                error_log("[SEARCH MODE] モード判定失敗、または映画に関する質問ではありませんでした。");
                return response()->json([
                    'status' => 'success',
                    'reply' => '申し訳ありません、映画に関するご質問ではないようですね。映画のおすすめについてお気軽にお尋ねください！',
                    'movies' => []
                ]);
            }

            $tmdbResults = [];

            // 2. モードに応じて検索を実行する
            if ($strategy['mode'] === 'title' && !empty($strategy['titles'])) {
                error_log("\n=======================================================");
                error_log("[SEARCH MODE] 🎯 Titleモード (ピンポイント検索) を使用します");
                error_log("[EXTRACTED TITLES] " . implode(", ", $strategy['titles']));
                error_log("=======================================================\n");

                // Titleモード: AIが推測したタイトルで検索
                // ランダム性を出すためにシャッフルする
                shuffle($strategy['titles']);
                
                foreach ($strategy['titles'] as $title) {
                    if (count($tmdbResults) >= 3) break;
                    
                    $title = trim($title);
                    if (!empty($title)) {
                        $results = $this->tmdbService->searchMovies($title, 1);
                        if (!empty($results)) {
                            $tmdbResults = array_merge($tmdbResults, $results);
                            $tmdbResults = array_unique($tmdbResults, SORT_REGULAR);
                            $tmdbResults = array_slice($tmdbResults, 0, 3);
                        }
                    }
                }
            } else if ($strategy['mode'] === 'discover' && !empty($strategy['discover_params'])) {
                error_log("\n=======================================================");
                error_log("[SEARCH MODE] 🌍 Discoverモード (データベース検索) を使用します");
                error_log("[EXTRACTED PARAMS] " . json_encode($strategy['discover_params'], JSON_UNESCAPED_UNICODE));
                error_log("=======================================================\n");

                // Discoverモード: TMDBのパラメータ検索
                $tmdbResults = $this->tmdbService->discoverMovies($strategy['discover_params'], 3);
            }

            // 3. TMDbの検索結果をプロンプトに埋め込んで、LLMに最終回答を作らせる
            $context = empty($tmdbResults) ? "（関連する映画データは見つかりませんでした）" : json_encode($tmdbResults, JSON_UNESCAPED_UNICODE);
            
            $finalPrompt = "ユーザーの要望: 「{$userMessage}」\n\n";
            $finalPrompt .= "以下の【実在する映画データ】をベースに、ユーザーの要望に沿っておすすめの映画を提案してください。\n";
            $finalPrompt .= "【実在する映画データ】\n{$context}\n\n";
            $finalPrompt .= "※注意事項：\n";
            $finalPrompt .= "1. 上記の映画データが空の場合や、要望に合わない場合は「申し訳ありません、お探し条件にぴったり合う映画が見つかりませんでした。別の条件で探してみましょうか？」のように、自然で丁寧なコンシェルジュとして返答してください。\n";
            $finalPrompt .= "2. 決して自分で実在しない映画を作ったり、存在しない架空のあらすじを捏造して語ったりしないでください。\n";
            $finalPrompt .= "3. あなたは映画専門のコンシェルジュです。天気、ニュース、プログラミングなど、映画検索や提案に直接関係のない一般的な質問や雑談には絶対に答えないでください。無関係な質問には必ず「私は映画専門のコンシェルジュですので、映画についてお尋ねください」とだけ返答してください。\n";
            $finalPrompt .= "4. 出力は必ず以下のJSON形式のみとし、その他のテキストは一切含めないでください。\n";
            $finalPrompt .= "5. general_replyは、映画データに登場する最も評価の高い作品について軽く触れる、映画鑑賞を促す言葉など、正しい日本語でバリエーション豊かにしてください。必ずしも全ての作品に言及する必要はありませんが、選定理由と関連性を持たせてください。\n";
            $finalPrompt .= "{\n";
            $finalPrompt .= "  \"general_reply\": \"ユーザーへの全体的な挨拶や提案の言葉（※毎回異なる気の利いた文章を出力してください）\"\n";
            $finalPrompt .= "}\n";

            $response = $this->executeWithCascadingFallback(
                function ($model) use ($finalPrompt, $history) {
                    return $this->llmService->ask($finalPrompt, $history, $model, true);
                },
                function () use ($finalPrompt, $history) {
                    return $this->fallbackLlmService->ask($finalPrompt, $history, 'llama-3.1-8b-instant', true);
                }
            );
            // LLMのレスポンスからJSON部分のみを抽出する（マークダウン等が含まれている対策）
            $jsonString = $response;
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $jsonString = $matches[0];
            }
            $responseData = json_decode($jsonString, true) ?? [];
            
            // 取得したおすすめコメントをTMDBのデータにマージする処理は削除しました

            return response()->json([
                'status' => 'success',
                'reply' => $responseData['general_reply'] ?? 'おすすめの映画はこちらです！',
                'movies' => $tmdbResults
            ]);
        } catch (\Exception $e) {
            \Log::error('ChatController Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => '申し訳ありません、現在システムが混み合っております。少し時間をおいてから再度お試しください。'
            ], 500);
        }
    }

    /**
     * 指定された順番でGeminiモデルを試し、全て失敗した場合はGroqにフォールバックする
     */
    private function executeWithCascadingFallback(callable $geminiAction, callable $groqAction)
    {
        $geminiModels = [
            'gemini-2.0-flash-lite',
            'gemini-2.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash-lite',
            'gemini-2.0-flash',
            'gemini-2.5-flash',
            'gemini-3.0-flash',
            'gemini-3.5-flash',
            'gemini-3.6-flash'
        ];

        foreach ($geminiModels as $model) {
            try {
                $result = $geminiAction($model);
                error_log("[LLM MODEL] ✅ 成功: {$model} を使用しました");
                return $result;
            } catch (\Exception $e) {
                error_log("[LLM MODEL] ⚠️ 失敗: {$model} -> 次のモデルへ切り替えます");
                // 次のモデルへ
                continue;
            }
        }

        error_log("[LLM MODEL] 🚨 全Geminiモデル失敗 -> 最後の砦 Groq (Llama 3) を使用します");
        return $groqAction();
    }
}
