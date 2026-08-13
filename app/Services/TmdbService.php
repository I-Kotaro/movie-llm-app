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

        // あらすじがない映画を除外
        $extractedMovies = array_filter($extractedMovies, function($item) {
            return !empty(trim($item['overview'] ?? ''));
        });

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
                    'append_to_response' => 'credits,watch/providers'
                ]);

            $cast = [];
            $providers = [];
            $providerLink = null;
            if ($detailResponse->successful()) {
                $detailData = $detailResponse->json();
                $credits = $detailData['credits']['cast'] ?? [];
                // 主要キャストを最大3名取得
                foreach (array_slice($credits, 0, 3) as $actor) {
                    $cast[] = $actor['name'];
                }

                // 配信プロバイダー情報の取得 (日本: JP, サブスク: flatrate, レンタル: rent, 購入: buy)
                $jpProviders = $detailData['watch/providers']['results']['JP'] ?? [];
                $providerLink = $jpProviders['link'] ?? null;
                $flatrate = $jpProviders['flatrate'] ?? [];
                $rent = $jpProviders['rent'] ?? [];
                $buy = $jpProviders['buy'] ?? [];
                
                $providerMap = [];
                foreach (array_merge($flatrate, $rent, $buy) as $provider) {
                    $providerMap[$provider['provider_id']] = [
                        'provider_name' => $provider['provider_name'],
                        'logo_url' => 'https://image.tmdb.org/t/p/w92' . $provider['logo_path'],
                    ];
                }
                $providers = array_values($providerMap);
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
                'providers' => $providers,
                'provider_link' => $providerLink,
            ];
        }

        return $movies;
    }

    /**
     * TMDBのDiscover APIを使って、パラメータ条件に合う映画を検索します。
     *
     * @param array $params 検索パラメータ (例: ['with_genres' => '28', 'primary_release_year' => 2026])
     * @param int $limit 取得件数
     * @return array
     */
    public function discoverMovies(array $params, int $limit = 3): array
    {
        if (empty($this->apiKey)) {
            \Log::warning('TMDb API key is not set. Searching is disabled.');
            return [];
        }

        // デフォルトのパラメータを設定
        $defaultParams = [
            'language' => 'ja-JP',
            'sort_by' => 'popularity.desc',
            'include_adult' => 'false',
            'include_video' => 'false',
            'page' => rand(1, 3), // バリエーションを持たせるため、上位1〜3ページからランダムに取得
        ];

        // $params をマージ
        $queryParams = array_merge($defaultParams, $params);

        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->get("{$this->baseUrl}/discover/movie", $queryParams);

        if ($response->failed()) {
            \Log::error('TMDb Discover API Error: ' . $response->body());
            return [];
        }

        $resultsData = $response->json('results', []);
        
        // ポスターがないものも基本含める（フロントエンド側でNO IMAGE対応済み）
        // あらすじがない映画は除外する
        $extractedMovies = array_filter($resultsData, function($item) {
            return !empty(trim($item['overview'] ?? ''));
        });

        // ランダム性を高めるため、取得したリストからシャッフルする
        shuffle($extractedMovies);

        $movies = [];
        foreach (array_slice($extractedMovies, 0, $limit) as $item) {
            $posterUrl = isset($item['poster_path']) 
                ? 'https://image.tmdb.org/t/p/w500' . $item['poster_path'] 
                : null;

            // キャスト情報を取得
            $movieId = $item['id'];
            $detailResponse = Http::withToken($this->apiKey)
                ->timeout(10)
                ->get("{$this->baseUrl}/movie/{$movieId}", [
                    'language' => 'ja-JP',
                    'append_to_response' => 'credits,watch/providers'
                ]);

            $cast = [];
            $providers = [];
            $providerLink = null;
            if ($detailResponse->successful()) {
                $detailData = $detailResponse->json();
                $credits = $detailData['credits']['cast'] ?? [];
                foreach (array_slice($credits, 0, 3) as $actor) {
                    $cast[] = $actor['name'];
                }

                // 配信プロバイダー情報の取得 (日本: JP, サブスク: flatrate, レンタル: rent, 購入: buy)
                $jpProviders = $detailData['watch/providers']['results']['JP'] ?? [];
                $providerLink = $jpProviders['link'] ?? null;
                $flatrate = $jpProviders['flatrate'] ?? [];
                $rent = $jpProviders['rent'] ?? [];
                $buy = $jpProviders['buy'] ?? [];
                
                $providerMap = [];
                foreach (array_merge($flatrate, $rent, $buy) as $provider) {
                    $providerMap[$provider['provider_id']] = [
                        'provider_name' => $provider['provider_name'],
                        'logo_url' => 'https://image.tmdb.org/t/p/w92' . $provider['logo_path'],
                    ];
                }
                $providers = array_values($providerMap);
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
                'providers' => $providers,
                'provider_link' => $providerLink,
            ];
        }

        return $movies;
    }
}
