<?php

namespace App\Http\Controllers;

use App\Services\AiAssistantService;
use App\Services\RateLimitedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function chat(Request $request, AiAssistantService $ai): JsonResponse
    {
        $data = $request->validate([
            'message'         => ['required', 'string', 'max:2000'],
            'history'         => ['sometimes', 'array', 'max:20'],
            'history.*.role'  => ['required_with:history', 'string', 'in:user,model'],
            'history.*.text'  => ['required_with:history', 'string', 'max:8000'],
        ]);

        try {
            $result = $ai->chat($data['message'], $data['history'] ?? []);
            return response()->json([
                'ok'         => true,
                'text'       => $result['text'],
                'tools_used' => $result['tools_used'],
            ]);
        } catch (RateLimitedException $e) {
            return response()->json([
                'ok'          => false,
                'rate_limit'  => true,
                'retry_after' => $e->retryAfterSeconds,
                'error'       => $e->getMessage(),
            ], 429);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
