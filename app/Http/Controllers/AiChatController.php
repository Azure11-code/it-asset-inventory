<?php

namespace App\Http\Controllers;

use App\Services\AiAssistantService;
use App\Services\AiServiceException;
use App\Services\RateLimitedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        } catch (AiServiceException $e) {
            // Already written for the user, and known not to contain the API key.
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            // Anything else is a bug on our side. The exception text can contain the
            // request URL, credentials or SQL, so it stays in the log.
            Log::error('AI assistant failed', [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'ok'    => false,
                'error' => 'Something went wrong on our side while answering. '
                           . 'Please try again — if it keeps happening, check storage/logs on the server.',
            ], 500);
        }
    }
}
