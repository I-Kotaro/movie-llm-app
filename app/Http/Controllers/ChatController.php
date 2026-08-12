<?php

namespace App\Http\Controllers;

use App\Services\MovieLlmService;
use App\Services\TmdbService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected MovieLlmService $movieLlmService;
    protected TmdbService $tmdbService;

    public function __construct(MovieLlmService $movieLlmService, TmdbService $tmdbService)
    {
        $this->movieLlmService = $movieLlmService;
        $this->tmdbService = $tmdbService;
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
            // 1. LLMにユーザーの入力から最適な映画タイトルを複数推測させる
            $suggestedTitles = $this->movieLlmService->extractSearchQuery($userMessage, $history);
            \Log::info('LLMが推測した映画タイトル: ' . $suggestedTitles);

            // 映画と関係ない質問の場合は、検索せずにすぐに返す
            if (trim($suggestedTitles) === 'NO_MOVIE_QUERY') {
                $finalPrompt = "ユーザーの発言: 「{$userMessage}」\n\nあなたは映画コンシェルジュです。ユーザーから映画に全く関係のない発言（天気、挨拶、雑談など）が来ました。優しく丁寧に、自分は映画についての質問にしか答えられない旨を伝えて、映画の話題に誘導してください。";
                $response = $this->movieLlmService->ask($finalPrompt, $history);
                return response()->json([
                    'status' => 'success',
                    'reply' => $response,
                    'movies' => []
                ]);
            }

            // 2. 推測された各タイトルでTMDbを検索する
            $tmdbResults = [];
            $titles = explode(',', $suggestedTitles);
            foreach (array_slice($titles, 0, 3) as $title) {
                $title = trim($title);
                if (!empty($title)) {
                    $results = $this->tmdbService->searchMovies($title, 2); // 複数ヒットするように2件取得
                    if (!empty($results)) {
                        $tmdbResults = array_merge($tmdbResults, $results);
                    }
                }
            }
            
            // 重複を排除し、最大5件に制限（LLMのトークン節約のため）
            $tmdbResults = array_unique($tmdbResults, SORT_REGULAR);
            $tmdbResults = array_slice($tmdbResults, 0, 5);

            // 3. TMDbの検索結果をプロンプトに埋め込んで、LLMに最終回答を作らせる
            $context = empty($tmdbResults) ? "（関連する映画データは見つかりませんでした）" : json_encode($tmdbResults, JSON_UNESCAPED_UNICODE);
            
            $finalPrompt = "ユーザーの要望: 「{$userMessage}」\n\n";
            $finalPrompt .= "以下の【実在する映画データ】のみを参考に、ユーザーの要望に沿っておすすめの映画を提案してください。\n";
            $finalPrompt .= "【実在する映画データ】\n{$context}\n\n";
            $finalPrompt .= "※注意事項：\n";
            $finalPrompt .= "1. 上記の映画データが空の場合や、要望に合わない場合は「申し訳ありません、お探し条件にぴったり合う映画が見つかりませんでした。別の条件で探してみましょうか？」のように、自然で丁寧なコンシェルジュとして返答してください。\n";
            $finalPrompt .= "2. 決して自分で実在しない映画を作ったり、嘘のあらすじを語ったりしないでください。\n";
            $finalPrompt .= "3. 映画のあらすじやシーンについて、上記の【実在する映画データ】に記載されていない内容を勝手に想像して捏造しないでください。記載されている事実のみに基づいておすすめの理由を説明してください。\n";
            $finalPrompt .= "4. 可能な限り複数の映画（最大3つ）を提案してください。\n";

            $response = $this->movieLlmService->ask($finalPrompt, $history);
            return response()->json([
                'status' => 'success',
                'reply' => $response,
                'movies' => $tmdbResults
            ]);
        } catch (\Exception $e) {
            \Log::error('LLM API Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => '申し訳ありません。現在AIがお返事できない状態です。'
            ], 500);
        }
    }
}
