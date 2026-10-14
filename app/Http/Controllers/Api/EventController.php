<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        return response()->json(
            Event::orderBy('start')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'description' => ['nullable', 'string'],
        ]);

        $event = Event::create($validated);

        return response()->json($event, 201);
    }
}
