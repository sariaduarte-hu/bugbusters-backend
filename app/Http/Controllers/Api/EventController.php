<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = Event::with(['creator', 'attendees'])->orderBy('start')->get();

        return response()->json(
            $events->map(fn (Event $event) => $this->format($event, $request->user()->id))
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

        return response()->json($this->respondData($event, $request), 201);
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

    public function signup(Request $request, Event $event): JsonResponse
    {
        if (($event->end ?? $event->start)->isPast()) {
            return response()->json(['message' => 'Dit evenement is al afgelopen.'], 422);
        }

        $event->attendees()->syncWithoutDetaching([$request->user()->id]);

        return response()->json($this->respondData($event, $request));
    }

    public function leave(Request $request, Event $event): JsonResponse
    {
        $event->attendees()->detach($request->user()->id);

        return response()->json($this->respondData($event, $request));
    }

    private function respondData(Event $event, Request $request): array
    {
        return $this->format($event->load(['creator', 'attendees']), $request->user()->id);
    }

    private function format(Event $event, int $userId): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->start->toJSON(),
            'end' => $event->end?->toJSON(),
            'description' => $event->description,
            'created_at' => $event->created_at?->toJSON(),
            'is_owner' => $event->user_id !== null && $event->user_id === $userId,
            'creator' => $event->creator?->toPerson(),
            'attendees' => $event->attendees->map(fn ($user) => $user->toPerson())->values(),
            'attendee_count' => $event->attendees->count(),
            'is_attending' => $event->attendees->contains('id', $userId),
        ];
    }
}