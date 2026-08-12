<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TmdbService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.themoviedb.org/3';

    public function __construct()
    {
        $this->apiKey = config('services.tmdb.key');
    }

    /**
     * TMDbで映画を検索します。
     *
     * @param string $query 検索キーワード
     * @param int $limit 取得件数
     * @return array 検索結果の配列
     */
    public function searchMovies(string $query, int $limit = 3): array
    {
        if (empty($this->apiKey)) {
            \Log::warning('TMDb API key is not set. Searching is disabled.');
            return [];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->get("{$this->baseUrl}/search/multi", [
                'query' => $query,
                'language' => 'ja-JP',
            ]);

        if ($response->failed()) {
            \Log::error('TMDb API Error: ' . $response->body());
            return [];
        }

        $resultsData = $response->json('results', []);
        
        // 人物(person)がヒットした場合は代表作(known_for)を映画として扱う
        $extractedMovies = [];
        foreach ($resultsData as $item) {
            if (isset($item['media_type'])) {
                if ($item['media_type'] === 'person' && !empty($item['known_for'])) {
                    foreach ($item['known_for'] as $kf) {
                        if (isset($kf['media_type']) && $kf['media_type'] === 'movie') {
                            $extractedMovies[] = $kf;
                        }
                    }
                } elseif ($item['media_type'] === 'movie') {
                    $extractedMovies[] = $item;
                }
            } else {
                // media_typeが無い場合はmovieとみなす
                $extractedMovies[] = $item;
            }
        }

        // 必要な情報だけを抽出して絞り込む（LLMのトークン節約のため）
        $movies = [];
        foreach (array_slice($extractedMovies, 0, $limit) as $item) {
            // ポスター画像URLを構築
            $posterUrl = isset($item['poster_path']) 
                ? 'https://image.tmdb.org/t/p/w500' . $item['poster_path'] 
                : null;

            // 映画IDを使って詳細情報（キャスト等）を取得
            $movieId = $item['id'];
            $detailResponse = Http::withToken($this->apiKey)
                ->timeout(10)
                ->get("{$this->baseUrl}/movie/{$movieId}", [
                    'language' => 'ja-JP',
                    'append_to_response' => 'credits'
                ]);

            $cast = [];
            if ($detailResponse->successful()) {
                $detailData = $detailResponse->json();
                $credits = $detailData['credits']['cast'] ?? [];
                // 主要キャストを最大3名取得
                foreach (array_slice($credits, 0, 3) as $actor) {
                    $cast[] = $actor['name'];
                }
            }

            $movies[] = [
                'id' => $movieId,
                'title' => $item['title'] ?? '不明',
                'original_title' => $item['original_title'] ?? null,
                'release_date' => $item['release_date'] ?? '不明',
                'overview' => $item['overview'] ?? 'あらすじなし',
                'vote_average' => $item['vote_average'] ?? 0,
                'poster_url' => $posterUrl,
                'cast' => empty($cast) ? '不明' : implode(', ', $cast),
            ];
        }

        return $movies;
    }
}
