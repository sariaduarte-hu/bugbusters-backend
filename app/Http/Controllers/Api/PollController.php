<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $polls = Poll::with($this->relations())
            ->latest()
            ->latest('id')
            ->get();

        return response()->json(
            $polls->map(fn (Poll $poll) => $this->format($poll, $request->user()->id))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'options' => ['required', 'array', 'min:2', 'max:20'],
            'options.*' => ['required', 'string', 'max:200'],
        ]);

        $options = array_map(
            fn ($option) => trim($option),
            $validated['options']
        );

        if (in_array('', $options, true)) {
            return response()->json([
                'message' => 'Antwoordmogelijkheden mogen niet leeg zijn.',
            ], 422);
        }

        $poll = DB::transaction(function () use ($request, $validated, $options) {
            $poll = Poll::create([
                'question' => trim($validated['question']),
                'color' => $validated['color'] ?? '#3498db',
                'user_id' => $request->user()->id,
            ]);

            foreach ($options as $option) {
                $poll->options()->create(['text' => $option]);
            }

            return $poll;
        });

        $poll->load($this->relations());

        return response()->json($this->format($poll, $request->user()->id), 201);
    }

    /**
     * Stem uitbrengen, wijzigen of intrekken (option_id = null).
     */
    public function vote(Request $request, Poll $poll): JsonResponse
    {
        $validated = $request->validate([
            'option_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->user()->id;
        $optionId = $validated['option_id'] ?? null;

        if ($optionId === null) {
            PollVote::where('poll_id', $poll->id)->where('user_id', $userId)->delete();
        } else {
            if (! $poll->options()->whereKey($optionId)->exists()) {
                return response()->json([
                    'message' => 'Deze optie hoort niet bij deze poll.',
                ], 422);
            }

            PollVote::updateOrCreate(
                ['poll_id' => $poll->id, 'user_id' => $userId],
                ['poll_option_id' => $optionId]
            );
        }

        $poll->load($this->relations());

        return response()->json($this->format($poll, $userId));
    }

    public function destroy(Request $request, Poll $poll): JsonResponse
    {
        if ($poll->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Je kunt alleen je eigen polls verwijderen.',
            ], 403);
        }

        $poll->delete();

        return response()->json(null, 204);
    }

    private function relations(): array
    {
        return [
            'creator.profile',
            'options' => fn ($q) => $q->orderBy('id'),
            'options.votes.user.profile' => fn ($q) => $q->orderBy('id'),
            'options.votes.user',
        ];
    }

    private function format(Poll $poll, int $userId): array
    {
        $myVote = $poll->options
            ->first(fn ($option) => $option->votes->contains('user_id', $userId))
            ?->id;

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'color' => $poll->color,
            'is_owner' => $poll->user_id === $userId,
            'creator' => $poll->creator?->toPerson(),
            'my_vote' => $myVote,
            'created_at' => $poll->created_at,
            'options' => $poll->options->map(fn ($option) => [
                'id' => $option->id,
                'text' => $option->text,
                'votes' => $option->votes->count(),
                'voters' => $option->votes
                    ->map(fn ($vote) => $vote->user?->toPerson())
                    ->filter()
                    ->values(),
            ])->values(),
        ];
    }
}