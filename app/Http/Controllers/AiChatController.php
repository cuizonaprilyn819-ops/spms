<?php

namespace App\Http\Controllers;

use App\Services\AiAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class AiChatController extends Controller
{
    public function __invoke(Request $request, AiAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'history' => ['sometimes', 'array', 'max:8'],
            'history.*' => ['array:role,content'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:6000'],
        ]);

        try {
            $reply = $assistant->draft('chat', [
                'history' => $data['history'] ?? [],
                'message' => $data['message'],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503)
                ->header('Cache-Control', 'no-store');
        }

        return response()->json(['reply' => $reply])->header('Cache-Control', 'no-store');
    }
}
