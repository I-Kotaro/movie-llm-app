<?php

namespace App\Http\Controllers;

use App\Services\MovieLlmService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected MovieLlmService $movieLlmService;

    public function __construct(MovieLlmService $movieLlmService)
    {
        $this->movieLlmService = $movieLlmService;
    }

    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        \Log::info('ユーザーからのリクエストを受け付けました: ' . $request->input('message'));

        try {
            $response = $this->movieLlmService->ask($request->input('message'));
            return response()->json([
                'status' => 'success',
                'reply' => $response
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
