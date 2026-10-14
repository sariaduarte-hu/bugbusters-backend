<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollController extends Controller
{
    public function index()
    {
        return response()->json(
            Poll::with('options')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
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

        $poll = DB::transaction(function () use ($validated, $options) {
            $poll = Poll::create([
                'question' => $validated['question'],
            ]);

            foreach ($options as $option) {
                $poll->options()->create([
                    'text' => $option,
                ]);
            }

            return $poll;
        });

        return response()->json($poll->load('options'), 201);
    }
}
