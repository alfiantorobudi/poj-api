<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ContentStrategist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\AgentResponse;

class TodoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $todo = (string) $request->input('todo');

        $agent = new ContentStrategist;

        if ($user = $request->user()) {
            if ($conversationId) {
                $agent = $agent->continue((string) $conversationId, as: $user);
            } else {
                $agent = $agent->forUser($user);
            }
        }

        /** @var AgentResponse $response */
        $response = $agent->prompt(
            "Analisis text input berikut ini. 
            Gunakan Tone of Voice : {$selectedTone}. 
            Platform Social Media : {$selectedPlatform}. 
            Text input :\n\n" . $transcript
        );

        return response()->json([
            'content' => (string) $response,
            'conversation_id' => $response->conversationId,
        ]);
    }
    public function edit(Request $request): JsonResponse
    {
        $transcript = (string) $request->input('transcript');
        $selectedTone = (string) $request->input('selectedTone');
        $selectedPlatform = (string) $request->input('selectedPlatform');
        $conversationId = $request->input('conversation_id');

        $agent = new ContentStrategist;

        if ($user = $request->user()) {
            if ($conversationId) {
                $agent = $agent->continue((string) $conversationId, as: $user);
            } else {
                $agent = $agent->forUser($user);
            }
        }

        /** @var AgentResponse $response */
        $response = $agent->prompt(
            "Analisis text input berikut ini. 
            Gunakan Tone of Voice : {$selectedTone}. 
            Platform Social Media : {$selectedPlatform}. 
            Text input :\n\n" . $transcript
        );

        return response()->json([
            'content' => (string) $response,
            'conversation_id' => $response->conversationId,
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $transcript = (string) $request->input('transcript');
        $selectedTone = (string) $request->input('selectedTone');
        $selectedPlatform = (string) $request->input('selectedPlatform');
        $conversationId = $request->input('conversation_id');

        $agent = new ContentStrategist;

        if ($user = $request->user()) {
            if ($conversationId) {
                $agent = $agent->continue((string) $conversationId, as: $user);
            } else {
                $agent = $agent->forUser($user);
            }
        }

        /** @var AgentResponse $response */
        $response = $agent->prompt(
            "Analisis text input berikut ini. 
            Gunakan Tone of Voice : {$selectedTone}. 
            Platform Social Media : {$selectedPlatform}. 
            Text input :\n\n" . $transcript
        );

        return response()->json([
            'content' => (string) $response,
            'conversation_id' => $response->conversationId,
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        $transcript = (string) $request->input('transcript');
        $selectedTone = (string) $request->input('selectedTone');
        $selectedPlatform = (string) $request->input('selectedPlatform');
        $conversationId = $request->input('conversation_id');

        $agent = new ContentStrategist;

        if ($user = $request->user()) {
            if ($conversationId) {
                $agent = $agent->continue((string) $conversationId, as: $user);
            } else {
                $agent = $agent->forUser($user);
            }
        }

        /** @var AgentResponse $response */
        $response = $agent->prompt(
            "Analisis text input berikut ini. 
            Gunakan Tone of Voice : {$selectedTone}. 
            Platform Social Media : {$selectedPlatform}. 
            Text input :\n\n" . $transcript
        );

        return response()->json([
            'content' => (string) $response,
            'conversation_id' => $response->conversationId,
        ]);
    }
}
