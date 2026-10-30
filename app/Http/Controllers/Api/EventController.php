<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Event::orderBy('start')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after:start'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $event = Event::create($validated + ['user_id' => $request->user()->id]);

        return response()->json($event->refresh(), 201);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        if ($event->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Je kunt alleen je eigen evenementen verwijderen.',
            ], 403);
        }

        $event->delete();

        return response()->json(null, 204);
    }
}
